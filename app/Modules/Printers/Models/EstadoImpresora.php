<?php

namespace App\Modules\Printers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EstadoImpresora extends Model
{
    protected $table = 'estados_impresora';

    protected $fillable = ['nombre', 'es_operativo'];

    protected function casts(): array
    {
        return ['es_operativo' => 'boolean'];
    }

    public function impresoras(): HasMany
    {
        return $this->hasMany(Impresora::class, 'estado_id');
    }
}