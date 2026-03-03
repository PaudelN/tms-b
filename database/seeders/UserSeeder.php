<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'extra' => [
                'phone' => '+1-555-0100',
                'avatar' => 'https://ui-avatars.com/api/?name=Admin+User',
                'bio' => 'System Administrator',
                'timezone' => 'UTC',
                'role' => 'admin',
            ],
        ]);

        // Create test user
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'extra' => [
                'phone' => '+1-555-0101',
                'avatar' => 'https://ui-avatars.com/api/?name=Test+User',
                'bio' => 'Test account for development',
                'timezone' => 'UTC',
                'role' => 'user',
            ],
        ]);

        // Create additional random users
        User::factory(20)->create();

        $this->command->info('✓ Users created successfully!');
        $this->command->info('  - Admin: admin@example.com');
        $this->command->info('  - Test: test@example.com');
        $this->command->info('  - Password: password (for all users)');
    }
}
