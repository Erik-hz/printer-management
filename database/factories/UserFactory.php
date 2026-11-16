<?php

namespace Database\Factories;

use App\Modules\Access\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'rol_id' => fn () => Rol::firstOrCreate(['nombre' => 'consulta'])->id,
            'activo' => true,
        ];
    }

    public function conRol(string $nombre): static
    {
        return $this->state(fn () => ['rol_id' => Rol::firstOrCreate(['nombre' => $nombre])->id]);
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}