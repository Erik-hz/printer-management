<?php

namespace App\Modules\Printers\Services;

use App\Modules\Printers\Models\Area;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Models\Lugar;
use App\Modules\Printers\Models\Marca;
use App\Modules\Printers\Models\ModeloImpresora;
use App\Modules\Printers\Models\Zona;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de eliminación de los catálogos de impresoras: un registro solo se
 * elimina si nada depende de él. Las impresoras eliminadas lógicamente cuentan
 * como dependencia, porque la base de datos las sigue referenciando.
 */
class CatalogoService
{
    public function eliminarZona(Zona $zona): void
    {
        if ($zona->lugares()->exists()) {
            $this->rechazar('No se puede eliminar la zona porque tiene lugares asociados.');
        }

        $zona->delete();
    }

    public function eliminarLugar(Lugar $lugar): void
    {
        if ($lugar->areas()->exists()) {
            $this->rechazar('No se puede eliminar el lugar porque tiene áreas asociadas.');
        }

        $lugar->delete();
    }

    public function eliminarArea(Area $area): void
    {
        if (Impresora::withTrashed()->where('area_id', $area->id)->exists()) {
            $this->rechazar('No se puede eliminar el área porque tiene impresoras asociadas. Desactívela en su lugar.');
        }

        $area->delete();
    }

    public function eliminarMarca(Marca $marca): void
    {
        if ($marca->modelos()->exists()) {
            $this->rechazar('No se puede eliminar la marca porque tiene modelos asociados.');
        }

        $marca->delete();
    }

    public function eliminarModelo(ModeloImpresora $modelo): void
    {
        if (Impresora::withTrashed()->where('modelo_id', $modelo->id)->exists()) {
            $this->rechazar('No se puede eliminar el modelo porque tiene impresoras asociadas.');
        }

        if ($modelo->consumibles()->exists()) {
            $this->rechazar('No se puede eliminar el modelo porque tiene consumibles compatibles registrados.');
        }

        $modelo->delete();
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['general' => $mensaje]);
    }
}