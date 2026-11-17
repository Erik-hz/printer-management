<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Requests\CambiarClaveRequest;
use App\Modules\Access\Services\UsuarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios)
    {
    }

    public function edit(): View
    {
        return view('perfil.clave');
    }

    public function update(CambiarClaveRequest $request): RedirectResponse
    {
        $this->usuarios->cambiarClave($request->user(), $request->validated('password'));

        return redirect()->route('perfil.clave')->with('estado', 'Contraseña actualizada.');
    }
}