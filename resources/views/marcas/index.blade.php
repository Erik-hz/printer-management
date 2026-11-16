@extends('layouts.app')

@section('titulo', 'Marcas')

@section('contenido')
    <h1 class="h3 mb-3">Marcas</h1>

    @include('catalogos._nuevo', [
        'accion' => route('marcas.store'),
        'etiqueta' => 'Nueva marca',
        'max' => 50,
    ])

    @include('catalogos._tabla', [
        'items' => $marcas,
        'max' => 50,
        'etiquetaConteo' => 'Modelos',
        'campoConteo' => 'modelos_count',
        'rutas' => ['actualizar' => 'marcas.update', 'eliminar' => 'marcas.destroy'],
    ])
@endsection