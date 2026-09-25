<?php

namespace Functional\Users\Database\Factories;

use Functional\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'display_name' => faker()->name(),
            'email' => faker()->unique()->email(),
            'email_verified_at' => now(),
            'password' => 'password',
            'timezone' => 'Europe/Paris',
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
