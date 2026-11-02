<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            AdminAreaSeeder::class,
        ]);

        // Local development admin (change the phone number to your own)
        User::firstOrCreate(
            ['phone' => '+8801700000000'],
            ['display_name' => 'Admin', 'role' => 'admin', 'phone_verified_at' => now()]
        );
    }
}