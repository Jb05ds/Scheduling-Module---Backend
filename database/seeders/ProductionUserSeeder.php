<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('PRODUCTION_USER_EMAIL')],
            [
                'name' => env('PRODUCTION_USER_NAME'),
                'password' => Hash::make(env('PRODUCTION_USER_PASSWORD')),
            ]
        );
    }
}