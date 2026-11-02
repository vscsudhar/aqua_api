<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = env('INITIAL_ADMIN_EMAIL', 'admin@marinaaqua.com');
        $adminUsername = env('INITIAL_ADMIN_USERNAME', 'admin');
        $adminPassword = env('INITIAL_ADMIN_PASSWORD', 'Admin@12345');

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Marina Aqua System Admin',
                'username' => $adminUsername,
                'phone' => '+91 98765 43210',
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'is_active' => true,
            ]
        );
    }
}
