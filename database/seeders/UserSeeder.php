<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Blackforce',
                'username' => 'admin',
                'email' => 'admin@blackforce.com',
                'password' => Hash::make('password'),
            ]
        );

    }
}
