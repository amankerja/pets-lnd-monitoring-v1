<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nik' => 'PANI-' . fake()->unique()->numerify('######'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'peserta',
            'department' => fake()->randomElement([
                'Mining Operations',
                'Engineering',
                'HSE & K3',
                'Plant & Maintenance',
                'HRD',
            ]),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'department' => 'HRD & Learning Center',
        ]);
    }

    public function lnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'lnd',
            'department' => 'Human Capital & LND',
        ]);
    }

    public function trainer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'trainer',
            'department' => 'Technical Training',
        ]);
    }

    public function deptAdmin(?int $orgId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'dept_admin',
            'organization_id' => $orgId,
            'department' => 'Mining Operations',
        ]);
    }

    public function contractorAdmin(?int $orgId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'contractor_admin',
            'organization_id' => $orgId,
            'department' => 'PT Maju Jaya Kontraktor',
        ]);
    }
}

