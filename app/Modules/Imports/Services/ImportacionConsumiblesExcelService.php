<?php

namespace App\Modules\Imports\Services;

use App\Modules\Imports\Models\ErrorImportacion;
use App\Modules\Imports\Models\Importacion;
use App\Modules\Inventory\Models\Consumible;
use App\Modules\Inventory\Models\TipoConsumible;
use App\Modules\Inventory\Services\ConsumibleService;
use App\Modules\Inventory\Services\InventarioService;
use App\Modules\Printers\Models\ModeloImpresora;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;
use ZipArchive;

class ImportacionConsumiblesExcelService
{
    private const FORMATO = 'inventario-consumibles';

    /** @return array{hoja: string, formato: string, filas_leidas: int, filas_omitidas: int, filas: array<int, array<string, mixed>>} */
    public function analizar(string $rutaArchivo): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw ValidationException::withMessages([
                'archivo' => 'El servidor PHP debe tener habilitada la extensión ZIP para leer archivos .xlsx.',
            ]);
        }

        $lector = IOFactory::createReader('Xlsx');
        $lector->setReadDataOnly(true);
        $coincidencias = [];
        foreach ($lector->listWorksheetNames($rutaArchivo) as $nombreHoja) {
            $lector->setLoadSheetsOnly($nombreHoja);
            $libro = $lector->load($rutaArchivo);
            $hoja = $libro->getSheetByName($nombreHoja);
            if ($hoja instanceof Worksheet && $this->tieneFormatoInventario($hoja)) {
                $coincidencias[] = $nombreHoja;
            }
            $libro->disconnectWorksheets();
        }

        if (count($coincidencias) !== 1) {
            throw ValidationException::withMessages([
                'archivo' => $coincidencias === []
                    ? 'No se encontró una hoja con el formato de INVENTARIO DE INSUMOS (encabezados en la primera fila).'
                    : 'El libro contiene varias hojas de INVENTARIO DE INSUMOS. Deje una sola hoja con ese formato.',
            ]);
        }

        $nombreHoja = $coincidencias[0];
        $lector->setLoadSheetsOnly($nombreHoja);
        $libro = $lector->load($rutaArchivo);
        $hoja = $libro->getSheetByName($nombreHoja);
        $analisis = $this->analizarHoja($hoja);
        $analisis['hoja'] = $nombreHoja;
        $libro->disconnectWorksheets();

        return $analisis;
    }

    private function tieneFormatoInventario(Worksheet $hoja): bool
    {
        $esperados = [
            'A' => 'TIPO',
            'B' => 'EN PRODUCCION',
            'C' => 'MODELO',
            'D' => 'CODIGO',
            'E' => 'COLOR',
            'F' => 'CANTIDAD',
            'G' => 'CANTIDAD DE IMPRESORAS',
            'H' => 'CONSUMO ANUAL',
        ];

        foreach ($esperados as $columna => $esperado) {
            $actual = $this->normalizarTexto((string) $hoja->getCell($columna.'1')->getFormattedValue());
            if ($actual !== $this->normalizarTexto($esperado)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{hoja: string, formato: string, filas_leidas: int, filas_omitidas: int, filas: array<int, array<string, mixed>>} */
    private function analizarHoja(Worksheet $hoja): array
    {
        $modelos = ModeloImpresora::query()->with('marca')->get();
        $tipos = TipoConsumible::query()->get()->keyBy(fn (TipoConsumible $tipo) => $this->normalizarTexto($tipo->nombre));
        $codigosExistentes = Consumible::query()->pluck('codigo')->map(fn ($codigo) => $this->normalizarTexto((string) $codigo))->flip();
        $productos = [];
        $filasLeidas = 0;
        $filasOmitidas = 0;
        $modelosContexto = [];

        for ($numeroFila = 2; $numeroFila <= $hoja->getHighestDataRow(); $numeroFila++) {
            $valores = [];
            foreach (range('A', 'L') as $columna) {
                $valores[$columna] = trim((string) $hoja->getCell($columna.$numeroFila)->getFormattedValue());
            }

            $codigoOriginal = $this->normalizarCodigo($valores['D']);
            if ($valores['A'] !== '') {
                $modelosContexto = $this->separarModelos($valores['A']);
            }

            if ($codigoOriginal === '') {
                if (count(array_filter($valores, fn (string $valor) => $valor !== '')) > 0) {
                    $filasOmitidas++;
                }

                continue;
            }
            $filasLeidas++;

            $etiqueta = $valores['E'];
            $tipoNombre = $this->tipoPara($etiqueta, $valores['C'], $modelosContexto);
            $color = $this->colorPara($etiqueta);
            $cantidad = $this->enteroNoNegativo($valores['F']);
            $familia = $this->normalizarCodigo($valores['C']);
            $modeloResultado = $this->resolverModelos($modelosContexto, $modelos);
            $clave = $this->normalizarCodigo($codigoOriginal).'|'.$tipoNombre.'|'.$color;

            $productos[$clave] ??= [
                'filas_origen' => [],
                'fila_origen' => $numeroFila,
                'codigo_original' => $codigoOriginal,
                'codigo' => $codigoOriginal,
                'tipo' => $tipoNombre,
                'color' => $color,
                'descripcion' => $this->descripcionPara($etiqueta, $familia, $color),
                'cantidad' => $cantidad,
                'modelos' => [],
                'modelo_ids' => [],
                'advertencias' => [],
                'errores' => [],
            ];

            $producto = &$productos[$clave];
            $producto['filas_origen'][] = $numeroFila;
            if ($cantidad === null) {
                $producto['errores'][] = [
                    'columna' => 'CANTIDAD',
                    'valor' => $valores['F'],
                    'mensaje' => 'Indique una cantidad entera mayor o igual a cero.',
                ];
            } elseif ($producto['cantidad'] !== null && $producto['cantidad'] !== $cantidad) {
                $producto['errores'][] = [
                    'columna' => 'CANTIDAD',
                    'valor' => $valores['F'],
                    'mensaje' => 'El mismo código y color aparece con stocks distintos; no se sumarán ni se elegirá uno automáticamente.',
                ];
            }

            $tipo = $tipos->get($this->normalizarTexto($tipoNombre));
            if ($tipo === null) {
                $producto['errores'][] = [
                    'columna' => 'TIPO',
                    'valor' => $tipoNombre,
                    'mensaje' => 'No existe ese tipo de consumible en el catálogo.',
                ];
            } else {
                $producto['tipo_consumible_id'] = (int) $tipo->id;
            }

            foreach ($modeloResultado['modelos'] as $modelo) {
                $producto['modelo_ids'][$modelo->id] = $modelo->id;
                $producto['modelos'][$modelo->id] = $modelo->marca->nombre.' '.$modelo->nombre;
            }
            foreach ($modeloResultado['no_encontrados'] as $noEncontrado) {
                $advertencia = "No se encontró el modelo compatible «{$noEncontrado}» en el catálogo.";
                $producto['advertencias'][$advertencia] = $advertencia;
            }
            unset($producto);
        }

        $gruposPorCodigo = [];
        foreach ($productos as $clave => $producto) {
            $gruposPorCodigo[$this->normalizarTexto($producto['codigo_original'])][] = $clave;
        }

        foreach ($gruposPorCodigo as $codigoOriginal => $claves) {
            if (count($claves) < 2) {
                continue;
            }

            foreach ($claves as $clave) {
                $producto = &$productos[$clave];
                $sufijo = mb_strtoupper(str_replace('_', '-', $producto['color']));
                $producto['codigo'] = Str::limit($producto['codigo_original'].'-'.$sufijo, 50, '');
                $producto['advertencias'][] = "El código {$codigoOriginal} se repite en varios colores; se añadió el sufijo -{$sufijo} para conservar la unicidad del catálogo.";
                unset($producto);
            }
        }

        $clavesPorCodigoImportado = [];
        foreach ($productos as $clave => $producto) {
            $clavesPorCodigoImportado[$this->normalizarTexto($producto['codigo'])][] = $clave;
        }
        foreach ($clavesPorCodigoImportado as $claves) {
            if (count($claves) < 2) {
                continue;
            }
            foreach ($claves as $clave) {
                $productos[$clave]['errores'][] = [
                    'columna' => 'CODIGO',
                    'valor' => $productos[$clave]['codigo'],
                    'mensaje' => 'Dos artículos del Excel producirían el mismo código; corrija el archivo antes de importar.',
                ];
            }
        }

        foreach ($productos as &$producto) {
            $producto['filas'] = implode(', ', $producto['filas_origen']);
            $producto['modelos'] = array_values($producto['modelos']);
            $producto['modelo_ids'] = array_values($producto['modelo_ids']);
            $producto['advertencias'] = array_values(array_unique($producto['advertencias']));
            $producto['errores'] = array_values($producto['errores']);

            if (isset($codigosExistentes[$this->normalizarTexto($producto['codigo'])])) {
                $producto['errores'][] = [
                    'columna' => 'CODIGO',
                    'valor' => $producto['codigo'],
                    'mensaje' => 'Ya existe un consumible activo con el código que se importaría.',
                ];
            }

            if ($producto['modelos'] === []) {
                $producto['advertencias'][] = 'Se importará sin modelos compatibles; revise esa relación después de la carga.';
            }

            $producto['errores'] = array_values(array_unique($producto['errores'], SORT_REGULAR));
        }
        unset($producto);

        return [
            'hoja' => $hoja->getTitle(),
            'formato' => self::FORMATO,
            'filas_leidas' => $filasLeidas,
            'filas_omitidas' => $filasOmitidas,
            'filas' => array_values($productos),
        ];
    }

    /** @param list<string> $modelosFuente */
    private function resolverModelos(array $modelosFuente, $modelos): array
    {
        $resueltos = [];
        $noEncontrados = [];

        foreach ($modelosFuente as $nombreFuente) {
            $claveFuente = $this->normalizarModelo($nombreFuente);
            if ($claveFuente === '') {
                continue;
            }

            $puntajes = [];
            foreach ($modelos as $modelo) {
                $nombre = $this->normalizarModelo($modelo->nombre);
                $marcaNombre = $this->normalizarModelo($modelo->marca->nombre.' '.$modelo->nombre);
                $puntaje = 0;
                foreach ([$nombre, $marcaNombre] as $alias) {
                    if ($alias === $claveFuente) {
                        $puntaje = max($puntaje, 10000 + strlen($alias));
                    } elseif (strlen($alias) >= 6 && strlen($claveFuente) >= 6
                        && (str_contains($claveFuente, $alias) || str_contains($alias, $claveFuente))) {
                        $puntaje = max($puntaje, min(strlen($alias), strlen($claveFuente)));
                    }
                }
                if ($puntaje > 0) {
                    $puntajes[$modelo->id] = [$puntaje, $modelo];
                }
            }

            if ($puntajes === []) {
                $noEncontrados[] = $nombreFuente;

                continue;
            }

            $maximo = max(array_column($puntajes, 0));
            $mejores = array_filter($puntajes, fn (array $item) => $item[0] === $maximo);
            if (count($mejores) !== 1) {
                $noEncontrados[] = $nombreFuente;

                continue;
            }

            $modelo = array_values($mejores)[0][1];
            $resueltos[$modelo->id] = $modelo;
        }

        return ['modelos' => array_values($resueltos), 'no_encontrados' => $noEncontrados];
    }

    private function separarModelos(string $texto): array
    {
        $nombres = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || preg_match('/^no\s+esta\s+en\s+producci[oó]n$/iu', $linea)) {
                continue;
            }
            $nombres[] = $linea;
        }

        return array_values(array_unique($nombres));
    }

    private function tipoPara(string $etiqueta, string $familia, array $modelos): string
    {
        $texto = $this->normalizarTexto($etiqueta.' '.$familia);
        $grupo = $this->normalizarTexto(implode(' ', $modelos));

        return match (true) {
            str_contains($texto, 'kit de mantenimiento') => 'Kit de mantenimiento',
            str_contains($texto, 'kit de transferencia') => 'Unidad de transferencia',
            str_contains($texto, 'kit de fusor') => 'Fusor',
            str_contains($texto, 'papel'), str_contains($texto, 'rollo') => 'Papel',
            str_contains($grupo, 'epson'), str_contains($grupo, 'brother') => 'Tinta',
            str_contains($grupo, 'plotter'), str_contains($grupo, 'designjet') => 'Cartucho de plotter',
            default => 'Tóner',
        };
    }

    private function colorPara(string $etiqueta): string
    {
        $texto = $this->normalizarTexto($etiqueta);
        $colores = [
            'negro' => ['BLACK', 'NEGRO'],
            'cian' => ['CYAN', 'CIAN'],
            'magenta' => ['MAGENTA'],
            'amarillo' => ['YELLOW', 'AMARILLO'],
        ];
        $coincidencias = [];

        foreach ($colores as $color => $etiquetas) {
            foreach ($etiquetas as $etiquetaColor) {
                if (preg_match('/(^|[^A-Z])'.preg_quote($etiquetaColor, '/').'([^A-Z]|$)/', $texto)) {
                    $coincidencias[] = $color;
                    break;
                }
            }
        }

        return count($coincidencias) === 1 ? $coincidencias[0] : 'no_aplica';
    }

    private function descripcionPara(string $etiqueta, string $familia, string $color): ?string
    {
        $esColor = $color !== 'no_aplica' && in_array($this->normalizarTexto($etiqueta), [
            'black', 'negro', 'cyan', 'cian', 'magenta', 'yellow', 'amarillo',
        ], true);
        $descripcion = $esColor
            ? ($familia !== '' && $this->normalizarCodigo($familia) !== $this->normalizarCodigo($etiqueta) ? $familia : null)
            : trim($etiqueta.($familia !== '' ? ' · '.$familia : ''));

        return $descripcion === '' ? null : Str::limit($descripcion, 255, '');
    }

    private function enteroNoNegativo(string $valor): ?int
    {
        if ($valor === '' || ! is_numeric($valor) || (float) $valor !== (float) (int) $valor || (int) $valor < 0) {
            return null;
        }

        return (int) $valor;
    }

    private function normalizarTexto(string $valor): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', Str::ascii($valor)) ?? $valor));
    }

    private function normalizarModelo(string $valor): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower(Str::ascii(trim($valor)))) ?? '';
    }

    private function normalizarCodigo(string $valor): string
    {
        $valor = preg_replace('/[\p{C}\x{200B}-\x{200D}\x{FEFF}]/u', '', trim($valor)) ?? trim($valor);

        return trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor);
    }

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

        $consumibles = app(ConsumibleService::class);
        $inventario = app(InventarioService::class);
        foreach ($analisis['filas'] as $fila) {
            if ($fila['errores'] !== []) {
                $this->registrarErrores($importacion, (string) $fila['filas'], $fila['errores']);
                $importacion->filas_con_error++;

                continue;
            }

            try {
                DB::transaction(function () use ($fila, $importacion, $usuarioId, $consumibles, $inventario): void {
                    $consumible = $consumibles->crear([
                        'tipo_consumible_id' => $fila['tipo_consumible_id'],
                        'codigo' => $fila['codigo'],
                        'descripcion' => $fila['descripcion'],
                        'color' => $fila['color'],
                        'rendimiento_paginas' => null,
                        'stock_minimo' => 0,
                        'activo' => true,
                        'modelos' => $fila['modelo_ids'],
                    ]);

                    if ($fila['cantidad'] > 0) {
                        $inventario->registrarEntradaManual(
                            (int) $consumible->id,
                            (int) $fila['cantidad'],
                            'Saldo inicial desde '.$importacion->archivo_nombre.', hoja '.$importacion->hoja.', filas '.$fila['filas'].'.',
                            $usuarioId,
                            importacionId: (int) $importacion->id,
                        );
                    }
                });

                $importacion->filas_importadas++;
            } catch (ValidationException $exception) {
                $errores = collect($exception->errors())
                    ->map(fn (array $mensajes, string $campo) => [
                        'columna' => $campo,
                        'valor' => null,
                        'mensaje' => implode(' ', $mensajes),
                    ])
                    ->values()
                    ->all();
                $this->registrarErrores($importacion, (string) $fila['filas'], $errores);
                $importacion->filas_con_error++;
            } catch (QueryException $exception) {
                if (! str_starts_with((string) $exception->getCode(), '23')) {
                    $this->marcarFallida($importacion);
                    throw $exception;
                }
                $this->registrarErrores($importacion, (string) $fila['filas'], [[
                    'columna' => 'CODIGO',
                    'valor' => $fila['codigo'],
                    'mensaje' => 'No se pudo guardar el consumible por una restricción de datos. Revise el código y vuelva a analizar el archivo.',
                ]]);
                $importacion->filas_con_error++;
            } catch (Throwable $exception) {
                $this->marcarFallida($importacion);
                throw $exception;
            }
        }

        $importacion->estado = $importacion->filas_con_error === 0 ? 'completada' : 'con_errores';
        $importacion->finalizada_en = now();
        $importacion->save();

        return $importacion;
    }

    private function marcarFallida(Importacion $importacion): void
    {
        $importacion->estado = 'fallida';
        $importacion->finalizada_en = now();
        $importacion->save();
    }

    private function registrarErrores(Importacion $importacion, string $filas, array $errores): void
    {
        foreach ($errores as $error) {
            ErrorImportacion::query()->create([
                'importacion_id' => $importacion->id,
                'fila' => (int) explode(',', $filas)[0],
                'columna' => $error['columna'] ?? null,
                'valor' => isset($error['valor']) ? mb_substr((string) $error['valor'], 0, 500) : null,
                'mensaje' => mb_substr((string) $error['mensaje'], 0, 500),
            ]);
        }
    }
}
