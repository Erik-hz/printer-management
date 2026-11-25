@php
    $colorClase = match ($nivel->color) {
        'negro' => 'negro',
        'cian' => 'cian',
        'magenta' => 'magenta',
        'amarillo' => 'amarillo',
        'tricolor' => 'tricolor',
        default => 'neutral',
    };
    $porcentajeReal = $nivel->nivel === null ? null : (float) $nivel->nivel * 100;
    $porcentaje = $porcentajeReal === null ? null : round($porcentajeReal, 1);
    $estadoNivel = match (true) {
        $porcentajeReal === null => 'sin_dato',
        $porcentajeReal <= config('monitoring.umbrales_nivel.critico_hasta', 10) => 'critico',
        $porcentajeReal <= config('monitoring.umbrales_nivel.bajo_hasta', 25) => 'bajo',
        default => 'normal',
    };
    $estadoClase = match ($estadoNivel) {
        'critico' => 'danger',
        'bajo' => 'warning',
        'normal' => 'success',
        default => 'secondary',
    };
    $estadoEtiqueta = match ($estadoNivel) {
        'critico' => 'Crítico',
        'bajo' => 'Bajo',
        'normal' => 'Normal',
        default => 'Sin dato',
    };
@endphp

<div class="nivel-tinta">
    <div class="d-flex justify-content-between align-items-center gap-2 small">
        <span class="d-inline-flex align-items-center gap-2">
            <span class="nivel-tinta__muestra nivel-tinta__muestra--{{ $colorClase }}" aria-hidden="true"></span>
            {{ $nivel->tipoConsumible->nombre }} {{ ucfirst($nivel->color) }}
        </span>
        @if ($porcentaje === null)
            <span class="badge text-bg-{{ $estadoClase }}">{{ $estadoEtiqueta }}</span>
        @else
            <span class="d-inline-flex align-items-center gap-2">
                <strong>{{ number_format($porcentaje, 1, ',', '.') }}%</strong>
                <span class="badge text-bg-{{ $estadoClase }}">{{ $estadoEtiqueta }}</span>
            </span>
        @endif
    </div>
    @if ($porcentaje !== null)
        <div class="progress nivel-tinta__progreso" role="progressbar"
             aria-label="Nivel de {{ $nivel->tipoConsumible->nombre }} {{ $nivel->color }}"
             aria-valuenow="{{ $porcentaje }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar nivel-tinta__barra nivel-tinta__barra--{{ $colorClase }}"
                 style="width: {{ $porcentaje }}%"></div>
        </div>
    @endif
</div>
