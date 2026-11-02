<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminAreaSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = [
            ['ঢাকা',       'Dhaka'],
            ['চট্টগ্রাম',   'Chattogram'],
            ['রাজশাহী',    'Rajshahi'],
            ['খুলনা',      'Khulna'],
            ['বরিশাল',     'Barishal'],
            ['সিলেট',      'Sylhet'],
            ['রংপুর',      'Rangpur'],
            ['ময়মনসিংহ',  'Mymensingh'],
        ];

        foreach ($divisions as [$bn, $en]) {
            DB::table('admin_areas')->updateOrInsert(
                ['level' => 'division', 'name_en' => $en],
                ['name_bn' => $bn, 'parent_id' => null,
                 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}