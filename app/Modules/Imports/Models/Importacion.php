<?php

namespace App\Modules\Imports\Models;

use App\Models\User;
use App\Modules\Monitoring\Models\Lectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Importacion extends Model
{
    protected $table = 'importaciones';

    protected $fillable = [
        'archivo_nombre',
        'hoja',
        'estado',
        'filas_leidas',
        'filas_importadas',
        'filas_con_error',
        'usuario_id',
        'iniciada_en',
        'finalizada_en',
    ];

    protected function casts(): array
    {
        return [
            'iniciada_en' => 'datetime',
            'finalizada_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function errores(): HasMany
    {
        return $this->hasMany(ErrorImportacion::class, 'importacion_id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class, 'importacion_id');
    }
}
