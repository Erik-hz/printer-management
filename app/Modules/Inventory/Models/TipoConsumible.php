<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Monitoring\Models\LecturaNivel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoConsumible extends Model
{
    protected $table = 'tipos_consumible';

    protected $fillable = ['nombre'];

    public function consumibles(): HasMany
    {
        return $this->hasMany(Consumible::class, 'tipo_consumible_id');
    }

    public function lecturasNivel(): HasMany
    {
        return $this->hasMany(LecturaNivel::class, 'tipo_consumible_id');
    }
}
