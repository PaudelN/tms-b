<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin user ─────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'extra' => [
                    'phone' => '+1-555-0100',
                    'avatar' => 'https://ui-avatars.com/api/?name=Admin+User',
                    'bio' => 'System Administrator',
                    'timezone' => 'UTC',
                    'role' => 'admin',
                ],
            ]
        );

        // ── Test user ──────────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'extra' => [
                    'phone' => '+1-555-0101',
                    'avatar' => 'https://ui-avatars.com/api/?name=Test+User',
                    'bio' => 'Test account for development',
                    'timezone' => 'UTC',
                    'role' => 'user',
                ],
            ]
        );

        // ── Random users (only create if we don't have enough yet) ─────────────
        $existing = User::whereNotIn('email', ['admin@example.com', 'test@example.com'])->count();
        $needed = max(0, 20 - $existing);

        if ($needed > 0) {
            User::factory($needed)->create();
        }

        $this->command->info('✓ Users seeded successfully!');
        $this->command->info('  - Admin: admin@example.com / password');
        $this->command->info('  - Test:  test@example.com / password');
    }
}
