<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Throwable;
use ZipArchive;

class ImportarSnapshotMonitoreo extends Command
{
    protected $signature = 'snapshot:import {archivo : Libro XLSX exportado desde SQL Server}';

    protected $description = 'Importa una instantánea de monitoreo a SQLite para el visor de solo lectura';

    private const HOJA = 'Monitoreo_SNMP';

    private const COLUMNAS = [
        'exportado_en_utc', 'impresora_id', 'impresora', 'direccion_ip', 'marca', 'modelo', 'estado',
        'zona', 'lugar', 'area', 'lectura_id', 'fecha_lectura', 'origen', 'contador_paginas',
        'motivo_sin_lectura', 'tipo_consumible_id', 'tipo_consumible', 'color', 'nivel', 'porcentaje', 'observaciones',
    ];

    public function handle(): int
    {
        $ruta = (string) $this->argument('archivo');
        if (! is_file($ruta)) {
            $this->error("No existe el archivo: {$ruta}");

            return self::FAILURE;
        }
        if (! class_exists(ZipArchive::class)) {
            $this->error('Habilita la extensión ZIP de PHP para leer archivos XLSX.');

            return self::FAILURE;
        }

        try {
            $lector = IOFactory::createReader('Xlsx');
            $lector->setReadDataOnly(true);
            if (! in_array(self::HOJA, $lector->listWorksheetNames($ruta), true)) {
                $this->error('El libro no contiene la hoja Monitoreo_SNMP.');

                return self::FAILURE;
            }

            $lector->setLoadSheetsOnly(self::HOJA);
            $libro = $lector->load($ruta);
            $hoja = $libro->getSheetByName(self::HOJA);
            $this->validarEncabezados($hoja);
            $filas = $this->leerFilas($hoja);
            $libro->disconnectWorksheets();
        } catch (Throwable $exception) {
            $this->error('No se pudo leer el snapshot: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($filas === []) {
            $this->error('La hoja no contiene filas de monitoreo; no se reemplazó el snapshot existente.');

            return self::FAILURE;
        }

        $conexion = DB::connection('snapshot');
        $directorio = dirname((string) config('database.connections.snapshot.database'));
        if (! is_dir($directorio) && ! mkdir($directorio, 0775, true) && ! is_dir($directorio)) {
            $this->error("No se pudo crear el directorio de SQLite: {$directorio}");

            return self::FAILURE;
        }
        $conexion->getPdo();
        if (! Schema::connection('snapshot')->hasTable('monitoreo_snapshot')) {
            Schema::connection('snapshot')->create('monitoreo_snapshot', function ($tabla): void {
                $tabla->bigIncrements('id');
                $tabla->string('exportado_en_utc', 40)->nullable();
                $tabla->unsignedBigInteger('impresora_id');
                $tabla->string('impresora', 150);
                $tabla->string('direccion_ip', 45)->nullable();
                $tabla->string('marca', 100)->nullable();
                $tabla->string('modelo', 150)->nullable();
                $tabla->string('estado', 80)->nullable();
                $tabla->string('zona', 120)->nullable();
                $tabla->string('lugar', 120)->nullable();
                $tabla->string('area', 120)->nullable();
                $tabla->unsignedBigInteger('lectura_id')->nullable();
                $tabla->string('fecha_lectura', 40)->nullable();
                $tabla->string('origen', 20)->nullable();
                $tabla->unsignedBigInteger('contador_paginas')->nullable();
                $tabla->string('motivo_sin_lectura', 150)->nullable();
                $tabla->unsignedBigInteger('tipo_consumible_id')->nullable();
                $tabla->string('tipo_consumible', 100)->nullable();
                $tabla->string('color', 20)->nullable();
                $tabla->decimal('nivel', 6, 4)->nullable();
                $tabla->decimal('porcentaje', 6, 2)->nullable();
                $tabla->text('observaciones')->nullable();
            });
        }

        $conexion->transaction(function () use ($conexion, $filas): void {
            $conexion->table('monitoreo_snapshot')->delete();
            foreach (array_chunk($filas, 500) as $lote) {
                $conexion->table('monitoreo_snapshot')->insert($lote);
            }
        });

        $this->info('Snapshot importado: '.count($filas).' filas, '.$this->conteoImpresoras($filas).' impresoras.');
        $this->line('El visor queda en modo de solo lectura; el archivo origen no se modifica.');

        return self::SUCCESS;
    }

    private function validarEncabezados($hoja): void
    {
        foreach (self::COLUMNAS as $indice => $esperado) {
            $letra = Coordinate::stringFromColumnIndex($indice + 1);
            $actual = trim((string) $hoja->getCell($letra.'1')->getValue());
            if ($actual !== $esperado) {
                throw new \RuntimeException("Encabezado incorrecto en {$letra}1; se esperaba '{$esperado}'.");
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function leerFilas($hoja): array
    {
        $filas = [];
        $ultimaFila = $hoja->getHighestDataRow();
        foreach (range(2, max(2, $ultimaFila)) as $numeroFila) {
            $valores = [];
            foreach (self::COLUMNAS as $indice => $nombre) {
                $letra = Coordinate::stringFromColumnIndex($indice + 1);
                $valor = $hoja->getCell($letra.$numeroFila)->getValue();
                $valores[$nombre] = is_string($valor) ? trim($valor) : $valor;
            }
            if (count(array_filter($valores, fn ($valor) => $valor !== null && $valor !== '')) === 0) {
                continue;
            }

            $impresoraId = $this->enteroOpcional($valores['impresora_id']);
            if ($impresoraId === null || $impresoraId < 1 || trim((string) $valores['impresora']) === '') {
                throw new \RuntimeException("Fila {$numeroFila}: se requiere impresora_id e impresora.");
            }

            $nivel = $this->numeroOpcional($valores['nivel']);
            $porcentaje = $this->numeroOpcional($valores['porcentaje']);
            if ($nivel !== null && ($nivel < 0 || $nivel > 1)) {
                throw new \RuntimeException("Fila {$numeroFila}: nivel debe estar entre 0 y 1.");
            }
            if ($porcentaje !== null && ($porcentaje < 0 || $porcentaje > 100)) {
                throw new \RuntimeException("Fila {$numeroFila}: porcentaje debe estar entre 0 y 100.");
            }

            $filas[] = [
                'exportado_en_utc' => $this->textoOpcional($valores['exportado_en_utc']),
                'impresora_id' => $impresoraId,
                'impresora' => trim((string) $valores['impresora']),
                'direccion_ip' => $this->textoOpcional($valores['direccion_ip']),
                'marca' => $this->textoOpcional($valores['marca']),
                'modelo' => $this->textoOpcional($valores['modelo']),
                'estado' => $this->textoOpcional($valores['estado']),
                'zona' => $this->textoOpcional($valores['zona']),
                'lugar' => $this->textoOpcional($valores['lugar']),
                'area' => $this->textoOpcional($valores['area']),
                'lectura_id' => $this->enteroOpcional($valores['lectura_id']),
                'fecha_lectura' => $this->textoOpcional($valores['fecha_lectura']),
                'origen' => $this->textoOpcional($valores['origen']),
                'contador_paginas' => $this->enteroOpcional($valores['contador_paginas']),
                'motivo_sin_lectura' => $this->textoOpcional($valores['motivo_sin_lectura']),
                'tipo_consumible_id' => $this->enteroOpcional($valores['tipo_consumible_id']),
                'tipo_consumible' => $this->textoOpcional($valores['tipo_consumible']),
                'color' => $this->textoOpcional($valores['color']),
                'nivel' => $nivel,
                'porcentaje' => $porcentaje,
                'observaciones' => $this->textoOpcional($valores['observaciones']),
            ];
        }

        return $filas;
    }

    private function numeroOpcional(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_string($valor)) {
            $valor = str_replace(',', '.', str_replace('%', '', trim($valor)));
        }

        return is_numeric($valor) ? (float) $valor : null;
    }

    private function enteroOpcional(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $entero = is_numeric($valor) && (float) $valor === (float) (int) $valor ? (int) $valor : false;
        if ($entero === false) {
            throw new \RuntimeException("Valor entero no válido en el snapshot: {$valor}.");
        }

        return $entero;
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }

    private function conteoImpresoras(array $filas): int
    {
        return count(array_unique(array_column($filas, 'impresora_id')));
    }
}
