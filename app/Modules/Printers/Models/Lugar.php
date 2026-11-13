<?php

namespace App\Modules\Printers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lugar extends Model
{
    protected $table = 'lugares';

    protected $fillable = ['zona_id', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'lugar_id');
    }
}