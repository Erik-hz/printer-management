<?php

namespace App\Modules\Imports\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ErrorImportacion extends Model
{
    protected $table = 'errores_importacion';

    protected $fillable = ['importacion_id', 'fila', 'columna', 'valor', 'mensaje'];

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }
}