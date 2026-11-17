<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Http\Requests\RestablecerClaveRequest;
use App\Modules\Access\Http\Requests\UsuarioRequest;
use App\Modules\Access\Models\Rol;
use App\Modules\Access\Services\UsuarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios)
    {
    }

    public function index(): View
    {
        return view('usuarios.index', [
            'usuarios' => User::with('rol')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('usuarios.form', [
            'usuario' => new User(),
            'roles' => Rol::orderBy('id')->get(),
        ]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $this->usuarios->crear($request->validated());

        return redirect()->route('usuarios.index')->with('estado', 'Usuario creado.');
    }

    public function edit(User $usuario): View
    {
        return view('usuarios.form', [
            'usuario' => $usuario,
            'roles' => Rol::orderBy('id')->get(),
        ]);
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $this->usuarios->actualizar($usuario, $request->validated());

        return redirect()->route('usuarios.index')->with('estado', 'Usuario actualizado.');
    }

    public function restablecerClave(RestablecerClaveRequest $request, User $usuario): RedirectResponse
    {
        $this->usuarios->cambiarClave($usuario, $request->validated('password'));

        return redirect()->route('usuarios.edit', $usuario)->with('estado', 'Contraseña restablecida.');
    }
}