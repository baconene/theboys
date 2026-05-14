<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'john.adrian.bacon2@gmail.com'],
            [
                'name' => 'John Adrian Bacon',
                'password' => Hash::make('&xzR2WsA&xzR2WsA'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('admin');
    }
}
