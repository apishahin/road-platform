<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * বাংলাদেশের ৬৪টি জেলা। বারবার চালালেও একই জেলা দুইবার ঢুকবে না।
 */
class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Dhaka' => [
                ['Dhaka', 'ঢাকা'], ['Faridpur', 'ফরিদপুর'], ['Gazipur', 'গাজীপুর'],
                ['Gopalganj', 'গোপালগঞ্জ'], ['Kishoreganj', 'কিশোরগঞ্জ'], ['Madaripur', 'মাদারীপুর'],
                ['Manikganj', 'মানিকগঞ্জ'], ['Munshiganj', 'মুন্সিগঞ্জ'], ['Narayanganj', 'নারায়ণগঞ্জ'],
                ['Narsingdi', 'নরসিংদী'], ['Rajbari', 'রাজবাড়ী'], ['Shariatpur', 'শরীয়তপুর'],
                ['Tangail', 'টাঙ্গাইল'],
            ],
            'Chattogram' => [
                ['Bandarban', 'বান্দরবান'], ['Brahmanbaria', 'ব্রাহ্মণবাড়িয়া'], ['Chandpur', 'চাঁদপুর'],
                ['Chattogram', 'চট্টগ্রাম'], ['Cumilla', 'কুমিল্লা'], ["Cox's Bazar", 'কক্সবাজার'],
                ['Feni', 'ফেনী'], ['Khagrachhari', 'খাগড়াছড়ি'], ['Lakshmipur', 'লক্ষ্মীপুর'],
                ['Noakhali', 'নোয়াখালী'], ['Rangamati', 'রাঙামাটি'],
            ],
            'Rajshahi' => [
                ['Bogura', 'বগুড়া'], ['Joypurhat', 'জয়পুরহাট'], ['Naogaon', 'নওগাঁ'],
                ['Natore', 'নাটোর'], ['Chapainawabganj', 'চাঁপাইনবাবগঞ্জ'], ['Pabna', 'পাবনা'],
                ['Rajshahi', 'রাজশাহী'], ['Sirajganj', 'সিরাজগঞ্জ'],
            ],
            'Khulna' => [
                ['Bagerhat', 'বাগেরহাট'], ['Chuadanga', 'চুয়াডাঙ্গা'], ['Jashore', 'যশোর'],
                ['Jhenaidah', 'ঝিনাইদহ'], ['Khulna', 'খুলনা'], ['Kushtia', 'কুষ্টিয়া'],
                ['Magura', 'মাগুরা'], ['Meherpur', 'মেহেরপুর'], ['Narail', 'নড়াইল'],
                ['Satkhira', 'সাতক্ষীরা'],
            ],
            'Barishal' => [
                ['Barguna', 'বরগুনা'], ['Barishal', 'বরিশাল'], ['Bhola', 'ভোলা'],
                ['Jhalokati', 'ঝালকাঠি'], ['Patuakhali', 'পটুয়াখালী'], ['Pirojpur', 'পিরোজপুর'],
            ],
            'Sylhet' => [
                ['Habiganj', 'হবিগঞ্জ'], ['Moulvibazar', 'মৌলভীবাজার'],
                ['Sunamganj', 'সুনামগঞ্জ'], ['Sylhet', 'সিলেট'],
            ],
            'Rangpur' => [
                ['Dinajpur', 'দিনাজপুর'], ['Gaibandha', 'গাইবান্ধা'], ['Kurigram', 'কুড়িগ্রাম'],
                ['Lalmonirhat', 'লালমনিরহাট'], ['Nilphamari', 'নীলফামারী'], ['Panchagarh', 'পঞ্চগড়'],
                ['Rangpur', 'রংপুর'], ['Thakurgaon', 'ঠাকুরগাঁও'],
            ],
            'Mymensingh' => [
                ['Jamalpur', 'জামালপুর'], ['Mymensingh', 'ময়মনসিংহ'],
                ['Netrokona', 'নেত্রকোনা'], ['Sherpur', 'শেরপুর'],
            ],
        ];

        $now = now();
        $added = 0;

        foreach ($data as $divisionEn => $districts) {
            $divisionId = DB::table('admin_areas')
                ->where('level', 'division')
                ->where('name_en', $divisionEn)
                ->value('id');

            if (! $divisionId) {
                $this->command?->warn("বিভাগ পাওয়া যায়নি: {$divisionEn} (এই বিভাগের জেলা বাদ গেল)");
                continue;
            }

            foreach ($districts as [$en, $bn]) {
                $exists = DB::table('admin_areas')
                    ->where('level', 'district')
                    ->where('parent_id', $divisionId)
                    ->where('name_en', $en)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('admin_areas')->insert([
                    'parent_id'  => $divisionId,
                    'level'      => 'district',
                    'name_bn'    => $bn,
                    'name_en'    => $en,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $added++;
            }
        }

        $this->command?->info("নতুন জেলা যোগ হয়েছে: {$added}টি");
    }
}