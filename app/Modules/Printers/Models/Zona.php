<?php

namespace App\Modules\Printers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zona extends Model
{
    protected $table = 'zonas';

    protected $fillable = ['nombre', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function lugares(): HasMany
    {
        return $this->hasMany(Lugar::class, 'zona_id');
    }
}