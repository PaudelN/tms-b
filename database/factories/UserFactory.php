<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $faker = \Faker\Factory::create('en_US');

        $firstName = $faker->firstName();
        $lastName  = $faker->lastName();
        $name      = $firstName . ' ' . $lastName;

        $timezones = [
            'UTC', 'America/New_York', 'America/Chicago',
            'America/Los_Angeles', 'Europe/London',
            'Europe/Paris', 'Asia/Tokyo', 'Asia/Kolkata',
        ];

        return [
            'name'              => $name,
            'email'             => strtolower($firstName . '.' . $lastName . rand(1, 999)) . '@example.com',
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'extra'             => [
                'phone'    => '+1' . rand(2002000000, 9999999999),
                'avatar'   => 'https://ui-avatars.com/api/?name=' . urlencode($name),
                'bio'      => $faker->sentence(8),
                'timezone' => $timezones[array_rand($timezones)],
            ],
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }
}
