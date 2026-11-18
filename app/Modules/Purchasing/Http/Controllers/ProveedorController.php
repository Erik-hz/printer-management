<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchasing\Http\Requests\ProveedorRequest;
use App\Modules\Purchasing\Models\PrecioConsumible;
use App\Modules\Purchasing\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProveedorController extends Controller
{
    public function index(): View
    {
        return view('proveedores.index', [
            'proveedores' => Proveedor::query()
                ->withCount('compras')
                ->orderBy('nombre')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('proveedores.form', ['proveedor' => new Proveedor]);
    }

    public function store(ProveedorRequest $request): RedirectResponse
    {
        Proveedor::query()->create($request->validated());

        return redirect()->route('proveedores.index')->with('estado', 'Proveedor creado.');
    }

    public function edit(Proveedor $proveedor): View
    {
        return view('proveedores.form', ['proveedor' => $proveedor]);
    }

    public function update(ProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $proveedor->update($request->validated());

        return redirect()->route('proveedores.index')->with('estado', 'Proveedor actualizado.');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $tieneCompras = $proveedor->compras()->exists();
        $tienePrecios = PrecioConsumible::query()->where('proveedor_id', $proveedor->id)->exists();

        if ($tieneCompras || $tienePrecios) {
            throw ValidationException::withMessages([
                'general' => 'No se puede eliminar un proveedor con compras o precios registrados. Desactívelo en su lugar.',
            ]);
        }

        $proveedor->delete();

        return redirect()->route('proveedores.index')->with('estado', 'Proveedor eliminado.');
    }
}
