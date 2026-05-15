<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_admin_user_with_hashed_password(): void
    {
        $this->seed(AdminUserSeeder::class);

        $user = DB::table('users')->where('username', 'admin')->first();

        $this->assertNotNull($user, 'Admin user should exist after seeding');
        $this->assertNotEquals('Aqaz', $user->password, 'SC-010: Literal password must never be stored');
        $this->assertTrue(Hash::check('Aqaz', $user->password), 'SC-010: Hash must verify against Aqaz');
    }

    public function test_seeder_is_idempotent_and_does_not_overwrite_password(): void
    {
        $this->seed(AdminUserSeeder::class);

        $firstHash = DB::table('users')->where('username', 'admin')->value('password');

        $this->seed(AdminUserSeeder::class);

        $count = DB::table('users')->where('username', 'admin')->count();
        $secondHash = DB::table('users')->where('username', 'admin')->value('password');

        $this->assertEquals(1, $count, 'SC-009: Only one admin row should exist after two seed runs');
        $this->assertEquals($firstHash, $secondHash, 'SC-009: Re-running seeder must not overwrite the password');
    }
}
