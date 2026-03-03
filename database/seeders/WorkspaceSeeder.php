<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding Workspaces...');

        // Fetch main users
        $admin = User::where('email', 'admin@example.com')->first();
        $testUser = User::where('email', 'test@example.com')->first();

        if (!$admin || !$testUser) {
            $this->command->error('Please run UserSeeder first!');
            return;
        }

        // --- 1. Create multiple workspaces for admin ---
        $adminWorkspaceNames = [
            'Marketing Campaign',
            'Website Redesign Project',
            'Mobile App Development',
            'Q1 Product Launch',
            'Team Building Activities',
        ];

        foreach ($adminWorkspaceNames as $baseName) {
            for ($i = 1; $i <= 10; $i++) {
                Workspace::factory()->create([
                    'name' => "$baseName #$i " . Str::random(5),
                    'user_id' => $admin->id,
                    'status' => 'active',
                ]);
            }
        }

        // --- 2. Archived workspaces for admin ---
        for ($i = 1; $i <= 2; $i++) {
            Workspace::factory()->create([
                'name' => "Archived Workspace #$i " . Str::random(5),
                'user_id' => $admin->id,
                'status' => 'archived',
            ]);
        }

        // --- 3. Workspaces for test user ---
        $testWorkspaceNames = [
            'Personal Projects',
            'Learning Resources',
            'Side Hustle Ideas',
        ];

        foreach ($testWorkspaceNames as $baseName) {
            for ($i = 1; $i <= 5; $i++) {
                Workspace::factory()->create([
                    'name' => "$baseName #$i " . Str::random(5),
                    'user_id' => $testUser->id,
                    'status' => 'active',
                ]);
            }
        }

        // --- 4. Random workspaces for other users ---
        $otherUsers = User::whereNotIn('email', ['admin@example.com', 'test@example.com'])->get();

        foreach ($otherUsers as $user) {
            $count = rand(5, 15); // each other user gets 5–15 workspaces
            for ($i = 1; $i <= $count; $i++) {
                Workspace::factory()->create([
                    'name' => "Random Workspace #$i " . Str::random(5),
                    'user_id' => $user->id,
                    'status' => 'active',
                ]);
            }
        }

        $this->command->info('✓ Workspaces seeded successfully!');
    }
}
