<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $username = config('currency.admin.username');

        if (User::where('username', $username)->exists()) {
            $this->command->line('Admin user already exists');

            return;
        }

        User::create([
            'name' => 'Admin',
            'email' => 'admin@local.test',
            'username' => $username,
            'password' => Hash::make(config('currency.admin.password')),
        ]);
    }
}
