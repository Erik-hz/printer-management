<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require dirname(__DIR__).'/vendor/autoload.php';

$ruta = dirname(__DIR__).'/database/exports/plantilla_snapshot_monitoreo.xlsx';
$encabezados = [
    'exportado_en_utc',
    'impresora_id',
    'impresora',
    'direccion_ip',
    'marca',
    'modelo',
    'estado',
    'zona',
    'lugar',
    'area',
    'lectura_id',
    'fecha_lectura',
    'origen',
    'contador_paginas',
    'motivo_sin_lectura',
    'tipo_consumible_id',
    'tipo_consumible',
    'color',
    'nivel',
    'porcentaje',
    'observaciones',
];

$libro = new Spreadsheet();
$instrucciones = $libro->getActiveSheet();
$instrucciones->setTitle('Instrucciones');
$lineas = [
    ['SNAPSHOT DE MONITOREO — PLANTILLA DE EXPORTACIÓN'],
    ['Esta plantilla no contiene lecturas reales. No la importes vacía como si fuera un respaldo.'],
    ['1. En SSMS, abre database/exports/monitoreo_snmp_export.sql y ejecútalo sobre la base laravel.'],
    ['2. Guarda el resultado de la consulta como CSV UTF-8 o copia el resultado completo a la hoja Monitoreo_SNMP, empezando en A1.'],
    ['3. Conserva todos los IDs, fechas ISO y valores decimales. Una lectura con varios consumibles ocupa varias filas, una por nivel.'],
    ['4. El archivo contiene hasta 30 lecturas SNMP/Powershell por impresora no eliminada; es una instantánea de solo lectura.'],
    ['5. Importa este libro con php artisan snapshot:import ruta/al/archivo.xlsx. El visor usa SQLite en modo de solo lectura.'],
    ['No se exportan usuarios, contraseñas, compras ni existencias; este libro está limitado al monitoreo de impresoras.'],
    ['Las filas de lecturas anteriores no se modifican por esta exportación.'],
];

foreach ($lineas as $indice => $linea) {
    $instrucciones->setCellValue('A'.($indice + 1), $linea[0]);
}
$instrucciones->mergeCells('A1:F1');
$instrucciones->getStyle('A1:F1')->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
$instrucciones->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('17365D');
$instrucciones->getStyle('A2:A9')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
$instrucciones->getColumnDimension('A')->setWidth(115);
$instrucciones->getRowDimension(1)->setRowHeight(28);
for ($fila = 2; $fila <= 9; $fila++) {
    $instrucciones->getRowDimension($fila)->setRowHeight(32);
}
$instrucciones->freezePane('A2');

$datos = $libro->createSheet();
$datos->setTitle('Monitoreo_SNMP');
foreach ($encabezados as $indice => $encabezado) {
    $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice + 1);
    $datos->setCellValue($letra.'1', $encabezado);
}
$ultimaColumna = $datos->getHighestColumn();
$datos->getStyle('A1:'.$ultimaColumna.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$datos->getStyle('A1:'.$ultimaColumna.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
$datos->getStyle('A1:'.$ultimaColumna.'1')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
$datos->getRowDimension(1)->setRowHeight(32);
$datos->freezePane('A2');
$datos->setAutoFilter('A1:'.$ultimaColumna.'1');

$anchos = [18, 14, 24, 18, 20, 28, 18, 20, 20, 22, 14, 24, 16, 20, 26, 20, 24, 16, 14, 16, 60];
foreach ($anchos as $indice => $ancho) {
    $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($indice + 1);
    $datos->getColumnDimension($letra)->setWidth($ancho);
}
$datos->getStyle('S2:S10000')->getNumberFormat()->setFormatCode('0.00%');
$datos->getStyle('T2:T10000')->getNumberFormat()->setFormatCode('0.00"%"');
$datos->getStyle('A1:'.$ultimaColumna.'1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$libro->setActiveSheetIndex(0);

(new Xlsx($libro))->save($ruta);
echo $ruta.PHP_EOL;
