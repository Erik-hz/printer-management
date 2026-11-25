<?php

namespace App\Modules\Monitoring\Models;

use App\Modules\Printers\Models\Impresora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EjecucionLecturaSnmp extends Model
{
    protected $table = 'ejecuciones_lecturas_snmp';

    protected $fillable = [
        'impresora_id',
        'usuario_id',
        'lote_id',
        'estado',
        'salida',
        'error',
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

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class, 'impresora_id')->withTrashed();
    }
}
