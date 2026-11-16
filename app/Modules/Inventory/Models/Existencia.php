<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Existencia extends Model
{
    public const CREATED_AT = null;

    protected $table = 'existencias';

    protected $primaryKey = 'consumible_id';

    public $incrementing = false;

    protected $fillable = [
        'consumible_id',
        'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'updated_at' => 'datetime',
        ];
    }

    public function consumible(): BelongsTo
    {
        return $this->belongsTo(Consumible::class, 'consumible_id');
    }
}
