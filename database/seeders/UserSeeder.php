<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        User::firstOrCreate(
            ['email' => 'admin@financeapp.local'],
            [
                'name' => 'Admin',
                'password' => env('DEV_ADMIN_PASSWORD', 'password'),
            ]
        );
    }
}
