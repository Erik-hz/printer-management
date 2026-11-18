<?php

namespace App\Modules\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;

class MotivoSinLectura extends Model
{
    protected $table = 'motivos_sin_lectura';

    protected $fillable = ['nombre'];
}
