<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class WorkspaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get admin and test users
        $admin = User::where('email', 'admin@example.com')->first();
        $testUser = User::where('email', 'test@example.com')->first();

        if (!$admin || !$testUser) {
            $this->command->error('Please run UserSeeder first!');
            return;
        }

        // Create workspaces for admin
        $adminWorkspaces = [
            'Marketing Campaign 2024',
            'Website Redesign Project',
            'Mobile App Development',
            'Q1 Product Launch',
            'Team Building Activities',
        ];

        foreach ($adminWorkspaces as $name) {
            Workspace::factory()
                ->forUser($admin)
                ->withName($name)
                ->active()
                ->create();
        }

        // Create archived workspaces for admin
        Workspace::factory()
            ->count(2)
            ->forUser($admin)
            ->archived()
            ->create();

        // Create workspaces for test user
        $testWorkspaces = [
            'Personal Projects',
            'Learning Resources',
            'Side Hustle Ideas',
        ];

        foreach ($testWorkspaces as $name) {
            Workspace::factory()
                ->forUser($testUser)
                ->withName($name)
                ->active()
                ->create();
        }

        // Create random workspaces for other users
        $otherUsers = User::whereNotIn('email', ['admin@example.com', 'test@example.com'])->get();

        foreach ($otherUsers as $user) {
            Workspace::factory()
                ->count(rand(3, 7))
                ->forUser($user)
                ->create();
        }

        $this->command->info('✓ Workspaces created successfully!');
    }
}
