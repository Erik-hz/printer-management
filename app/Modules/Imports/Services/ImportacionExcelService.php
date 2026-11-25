<?php

namespace App\Modules\Imports\Services;

use App\Modules\Imports\Models\ErrorImportacion;
use App\Modules\Imports\Models\Importacion;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Monitoring\Models\Lectura;
use App\Modules\Monitoring\Models\MotivoSinLectura;
use App\Modules\Monitoring\Services\LecturaService;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Support\DireccionIp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use ZipArchive;

class ImportacionExcelService
{
    private const HOJA = '29052025';
    private const NOMBRES_TIPO_TONER = ['Tóner', 'Toner'];

    private const FECHA_PORCENTAJE = '2025-03-31 00:00:00';

    private const FECHA_MOTIVO = '2025-05-29 00:00:00';

    private const COLUMNAS_CONSUMO = ['O', 'P', 'Q', 'R', 'S', 'T'];

    public function __construct(private readonly LecturaService $lecturas) {}

    /**
     * Lee únicamente los porcentajes de marzo y los marcadores de lectura sin dato.
     * Las cantidades de consumo anual y de mayo no se convierten en movimientos.
     *
     * @return array{hoja: string, filas_leidas: int, filas_omitidas: int, filas: array<int, array<string, mixed>>}
     */
    public function analizar(string $rutaArchivo): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages([
                'archivo' => 'El servidor PHP debe tener habilitada la extensión ZIP para leer archivos .xlsx (extension=zip).',
            ]);
        }

        $lector = IOFactory::createReader('Xlsx');
        $lector->setReadDataOnly(true);
        $nombresHojas = $lector->listWorksheetNames($rutaArchivo);

        if (! in_array(self::HOJA, $nombresHojas, true)) {
            throw ValidationException::withMessages([
                'archivo' => 'El Excel no contiene la hoja requerida «29052025».',
            ]);
        }

        $lector->setLoadSheetsOnly(self::HOJA);
        $libro = $lector->load($rutaArchivo);
        $hoja = $libro->getSheetByName(self::HOJA);

        if (! $hoja instanceof Worksheet) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo abrir la hoja «29052025».',
            ]);
        }

        $this->validarEncabezados($hoja);
        $impresoras = $this->mapasImpresoras();
        $tipoToner = TipoConsumible::query()
            ->whereIn('nombre', self::NOMBRES_TIPO_TONER)
            ->value('id');
        $motivos = MotivoSinLectura::query()->get()->keyBy(
            fn (MotivoSinLectura $motivo) => mb_strtolower(trim($motivo->nombre))
        );
        $lecturasExistentes = $this->clavesLecturasImportadas();
        $filas = [];
        $filasOmitidas = 0;
        $vistasEnArchivo = [];

        for ($numeroFila = 3; $numeroFila <= $hoja->getHighestDataRow(); $numeroFila++) {
            $valores = $this->valoresFila($hoja, $numeroFila);
            $porcentajeBruto = $valores['J'];
            $marcadores = $this->marcadores($valores);
            $tienePorcentaje = $porcentajeBruto !== null && trim((string) $porcentajeBruto) !== '';

            if (! $tienePorcentaje && $marcadores === []) {
                if ($this->filaTieneDatos($valores)) {
                    $filasOmitidas++;
                }

                continue;
            }

            $nombre = $this->texto($valores['D']);
            $direccionIp = DireccionIp::normalizar($this->texto($valores['G']));
            $impresora = $this->buscarImpresora($nombre, $direccionIp, $impresoras);
            $errores = [];
            $nivelesPorcentaje = [];
            $motivo = null;

            if ($impresora === null) {
                $errores[] = [
                    'columna' => 'NOMBRE/IP',
                    'valor' => $nombre ?: $direccionIp,
                    'mensaje' => 'No se encontró una impresora registrada por nombre o IP. Regístrela antes de importar.',
                ];
            }

            if ($tienePorcentaje) {
                $nivelesPorcentaje = $this->nivelesPorcentaje($porcentajeBruto, $this->texto($valores['C'])) ?? [];

                if ($nivelesPorcentaje === []) {
                    $errores[] = [
                        'columna' => 'Porcentaje Actual 03/2025',
                        'valor' => $this->texto($porcentajeBruto),
                        'mensaje' => 'El dato debe ser una fracción entre 0 y 1 o una lista de porcentajes por color.',
                    ];
                } elseif ($tipoToner === null) {
                    $errores[] = [
                        'columna' => 'TIPO',
                        'valor' => 'toner',
                        'mensaje' => 'No existe el tipo de consumible «toner» en el catálogo.',
                    ];
                }
            }

            $motivosEncontrados = array_unique(array_column($marcadores, 'motivo'));

            if (count($motivosEncontrados) > 1) {
                $errores[] = [
                    'columna' => 'CONSUMO 29/05/2025',
                    'valor' => implode(', ', array_column($marcadores, 'valor')),
                    'mensaje' => 'La fila contiene más de un motivo; la lectura solo permite un motivo por impresora y fecha.',
                ];
            } elseif ($marcadores !== []) {
                $marcador = $marcadores[0];
                $motivo = $marcador['motivo'];

                if (! $motivos->has($motivo)) {
                    $errores[] = [
                        'columna' => 'CONSUMO 29/05/2025',
                        'valor' => $marcador['valor'],
                        'mensaje' => "No existe el motivo «{$motivo}» en el catálogo.",
                    ];
                }
            }

            if ($impresora !== null) {
                $lecturas = [];

                if ($nivelesPorcentaje !== []) {
                    $lecturas[] = [
                        'fecha_lectura' => self::FECHA_PORCENTAJE,
                        'tipo' => 'nivel',
                        'niveles' => $nivelesPorcentaje,
                    ];
                }

                if ($motivo !== null) {
                    $lecturas[] = [
                        'fecha_lectura' => self::FECHA_MOTIVO,
                        'tipo' => 'motivo',
                        'motivo' => $motivo,
                        'valor_motivo' => $marcadores[0]['valor'],
                    ];
                }

                foreach ($lecturas as $lectura) {
                    $clave = $this->claveLectura((int) $impresora->id, $lectura['fecha_lectura']);

                    if (isset($vistasEnArchivo[$clave])) {
                        $errores[] = [
                            'columna' => 'NOMBRE',
                            'valor' => $nombre,
                            'mensaje' => 'La misma impresora aparece más de una vez para la misma fecha histórica.',
                        ];
                        break;
                    }

                    if (isset($lecturasExistentes[$clave])) {
                        $errores[] = [
                            'columna' => 'NOMBRE',
                            'valor' => $nombre,
                            'mensaje' => 'Ya existe una lectura importada para esta impresora y fecha.',
                        ];
                    } elseif ($errores === []) {
                        $vistasEnArchivo[$clave] = true;
                    }
                }
            } else {
                $lecturas = [];
            }

            $filas[] = [
                'fila' => $numeroFila,
                'impresora' => $nombre ?: '—',
                'impresora_id' => $impresora?->id,
                'lecturas' => $lecturas,
                'errores' => $errores,
            ];
        }

        $libro->disconnectWorksheets();

        return [
            'hoja' => self::HOJA,
            'filas_leidas' => count($filas),
            'filas_omitidas' => $filasOmitidas,
            'filas' => $filas,
        ];
    }

    /**
     * @param  array{hoja: string, filas_leidas: int, filas_omitidas: int, filas: array<int, array<string, mixed>>}  $analisis
     */
    public function ejecutar(array $analisis, string $nombreArchivo, int $usuarioId): Importacion
    {
        $importacion = Importacion::query()->create([
            'archivo_nombre' => $nombreArchivo,
            'hoja' => $analisis['hoja'],
            'estado' => 'en_proceso',
            'filas_leidas' => $analisis['filas_leidas'],
            'filas_importadas' => 0,
            'filas_con_error' => 0,
            'usuario_id' => $usuarioId,
            'iniciada_en' => now(),
        ]);

        $tipoToner = TipoConsumible::query()->whereIn('nombre', self::NOMBRES_TIPO_TONER)->first();

        foreach ($analisis['filas'] as $fila) {
            if ($fila['errores'] !== []) {
                $this->registrarErrores($importacion, $fila['fila'], $fila['errores']);
                $importacion->filas_con_error++;

                continue;
            }

            try {
                DB::transaction(function () use ($fila, $importacion, $tipoToner): void {
                    foreach ($fila['lecturas'] as $lectura) {
                        $attributes = [
                            'impresora_id' => $fila['impresora_id'],
                            'fecha_lectura' => $lectura['fecha_lectura'],
                            'origen' => 'importacion',
                            'contador_paginas' => null,
                            'motivo_sin_lectura_id' => null,
                            'observaciones' => "Importación Excel, hoja 29052025, fila {$fila['fila']}.",
                            'usuario_id' => null,
                            'importacion_id' => $importacion->id,
                            'niveles' => [],
                        ];

                        if ($lectura['tipo'] === 'nivel') {
                            $attributes['niveles'] = array_map(
                                fn (array $nivel) => [
                                    'tipo_consumible_id' => $tipoToner->id,
                                    'color' => $nivel['color'],
                                    'nivel' => $nivel['nivel'],
                                ],
                                $lectura['niveles']
                            );
                        } else {
                            $attributes['motivo_sin_lectura_id'] = MotivoSinLectura::query()
                                ->where('nombre', $lectura['motivo'])
                                ->firstOrFail()
                                ->id;
                        }

                        $this->lecturas->registrar($attributes);
                    }
                });

                $importacion->filas_importadas++;
            } catch (ValidationException $exception) {
                $this->registrarErrores(
                    $importacion,
                    $fila['fila'],
                    collect($exception->errors())
                        ->map(fn (array $mensajes, string $campo) => [
                            'columna' => $campo,
                            'valor' => null,
                            'mensaje' => implode(' ', $mensajes),
                        ])
                        ->values()
                        ->all()
                );
                $importacion->filas_con_error++;
            } catch (QueryException $exception) {
                if (! str_starts_with((string) $exception->getCode(), '23')) {
                    $importacion->estado = 'fallida';
                    $importacion->finalizada_en = now();
                    $importacion->save();

                    throw $exception;
                }

                $this->registrarErrores($importacion, $fila['fila'], [[
                    'columna' => null,
                    'valor' => null,
                    'mensaje' => 'No se pudo guardar la fila por una restricción de datos. Revise las referencias y vuelva a analizar el archivo.',
                ]]);
                $importacion->filas_con_error++;
            }
        }

        $importacion->estado = $importacion->filas_con_error === 0 ? 'completada' : 'con_errores';
        $importacion->finalizada_en = now();
        $importacion->save();

        return $importacion;
    }

    private function validarEncabezados(Worksheet $hoja): void
    {
        $nombre = mb_strtoupper(trim((string) $hoja->getCell('D2')->getFormattedValue()));
        $ip = mb_strtoupper(trim((string) $hoja->getCell('G2')->getFormattedValue()));
        $porcentaje = mb_strtoupper(trim((string) $hoja->getCell('J2')->getFormattedValue()));
        $fechaConsumo = mb_strtoupper(trim((string) $hoja->getCell('O1')->getFormattedValue()));
        $colorNegro = mb_strtoupper(trim((string) $hoja->getCell('O2')->getFormattedValue()));

        if (
            $nombre !== 'NOMBRE'
            || $ip !== 'IP'
            || ! str_contains($porcentaje, 'PORCENTAJE ACTUAL')
            || ! str_contains($fechaConsumo, '29/05/2025')
            || $colorNegro !== 'NEGRO'
        ) {
            throw ValidationException::withMessages([
                'archivo' => 'La estructura de la hoja «29052025» no coincide con la plantilla de importación esperada.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function valoresFila(Worksheet $hoja, int $fila): array
    {
        $columnas = ['C', 'D', 'G', 'J', ...self::COLUMNAS_CONSUMO];
        $valores = [];

        foreach ($columnas as $columna) {
            $valores[$columna] = $hoja->getCell("{$columna}{$fila}")->getValue();
        }

        return $valores;
    }

    /** @param  array<string, mixed>  $valores */
    private function filaTieneDatos(array $valores): bool
    {
        foreach ($valores as $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $valores
     * @return array<int, array{motivo: string, valor: string}>
     */
    private function marcadores(array $valores): array
    {
        $motivos = [];

        foreach (self::COLUMNAS_CONSUMO as $columna) {
            $valor = mb_strtoupper(trim((string) $valores[$columna]));

            if ($valor === 'SR') {
                $motivos[$columna] = ['motivo' => 'retirado', 'valor' => 'SR'];
            } elseif ($valor === 'DESC%') {
                $motivos[$columna] = ['motivo' => 'descuento', 'valor' => 'DESC%'];
            }
        }

        return array_values($motivos);
    }

    /**
     * @return array<int, array{color: string, nivel: float}>|null
     */
    private function nivelesPorcentaje(mixed $valor, string $tipoImpresion): ?array
    {
        if (is_numeric($valor)) {
            $numero = (float) $valor;

            if ($numero < 0 || $numero > 1) {
                return null;
            }

            return [[
                'color' => mb_strtoupper(trim($tipoImpresion)) === 'BN' ? 'negro' : 'no_aplica',
                'nivel' => round($numero, 4),
            ]];
        }

        $partes = array_map('trim', explode(',', $this->texto($valor)));

        if (count($partes) < 2 || count($partes) > 4) {
            return null;
        }

        $colores = ['negro', 'cian', 'magenta', 'amarillo'];
        $niveles = [];

        foreach ($partes as $indice => $parte) {
            $tienePorcentaje = str_ends_with($parte, '%');
            $numeroTexto = $tienePorcentaje ? substr($parte, 0, -1) : $parte;

            if (! is_numeric($numeroTexto)) {
                return null;
            }

            $numero = (float) $numeroTexto;

            if ($numero < 0 || $numero > 100) {
                return null;
            }

            $niveles[] = [
                'color' => $colores[$indice],
                'nivel' => round($numero / 100, 4),
            ];
        }

        return $niveles;
    }

    private function texto(mixed $valor): string
    {
        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /**
     * @return array{name: array<string, array<int, Impresora>>, ip: array<string, array<int, Impresora>>}
     */
    private function mapasImpresoras(): array
    {
        $porNombre = [];
        $porIp = [];

        foreach (Impresora::withTrashed()->get(['id', 'nombre', 'direccion_ip', 'deleted_at']) as $impresora) {
            $nombre = $this->normalizarNombre($impresora->nombre);
            $porNombre[$nombre][] = $impresora;

            $ip = DireccionIp::normalizar($impresora->direccion_ip);

            if (DireccionIp::esValida($ip)) {
                $porIp[$ip][] = $impresora;
            }
        }

        return ['name' => $porNombre, 'ip' => $porIp];
    }

    /** @param  array{name: array<string, array<int, Impresora>>, ip: array<string, array<int, Impresora>>}  $mapas */
    private function buscarImpresora(string $nombre, ?string $ip, array $mapas): ?Impresora
    {
        if ($nombre !== '' && $nombre !== '-') {
            $coincidencias = $mapas['name'][$this->normalizarNombre($nombre)] ?? [];
            $impresora = $this->resolverCoincidencias($coincidencias);

            if ($impresora !== null) {
                return $impresora;
            }
        }

        if (DireccionIp::esValida($ip)) {
            return $this->resolverCoincidencias($mapas['ip'][$ip] ?? []);
        }

        return null;
    }

    /** @param  array<int, Impresora>  $coincidencias */
    private function resolverCoincidencias(array $coincidencias): ?Impresora
    {
        $activas = array_values(array_filter($coincidencias, fn (Impresora $impresora) => $impresora->deleted_at === null));

        if (count($activas) === 1) {
            return $activas[0];
        }

        return count($activas) === 0 && count($coincidencias) === 1 ? $coincidencias[0] : null;
    }

    private function normalizarNombre(string $nombre): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($nombre)) ?? trim($nombre));
    }

    /** @return array<string, true> */
    private function clavesLecturasImportadas(): array
    {
        $claves = [];

        Lectura::query()
            ->where('origen', 'importacion')
            ->where(function ($consulta): void {
                $consulta
                    ->whereDate('fecha_lectura', substr(self::FECHA_PORCENTAJE, 0, 10))
                    ->orWhereDate('fecha_lectura', substr(self::FECHA_MOTIVO, 0, 10));
            })
            ->get(['impresora_id', 'fecha_lectura'])
            ->each(function (Lectura $lectura) use (&$claves): void {
                $claves[$this->claveLectura(
                    (int) $lectura->impresora_id,
                    $lectura->fecha_lectura->toDateTimeString()
                )] = true;
            });

        return $claves;
    }

    private function claveLectura(int $impresoraId, string $fecha): string
    {
        return $impresoraId.'|'.substr($fecha, 0, 10);
    }

    /**
     * @param  array<int, array{columna: ?string, valor: ?string, mensaje: string}>  $errores
     */
    private function registrarErrores(Importacion $importacion, int $fila, array $errores): void
    {
        foreach ($errores as $error) {
            ErrorImportacion::query()->create([
                'importacion_id' => $importacion->id,
                'fila' => $fila,
                'columna' => $error['columna'],
                'valor' => $error['valor'] === null ? null : mb_substr((string) $error['valor'], 0, 500),
                'mensaje' => mb_substr($error['mensaje'], 0, 500),
            ]);
        }
    }
}
