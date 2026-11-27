<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        DB::table('categories')->updateOrInsert(
            ['slug' => 'road-and-mobility'],
            [
                'parent_id' => null,
                'name_bn' => 'সড়ক ও নিরাপদ চলাচল',
                'name_en' => 'Roads and safe mobility',
                'sort' => 0,
                'is_active' => true,
                'is_reportable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $parentId = DB::table('categories')->where('slug', 'road-and-mobility')->value('id');

        $categories = [
            ['pothole',          'রাস্তায় গর্ত',               'Pothole'],
            ['damaged_road',     'ভাঙা/ক্ষতিগ্রস্ত রাস্তা',      'Broken or damaged road'],
            ['waterlogging',     'জলাবদ্ধতা',                  'Waterlogging'],
            ['utility_cut',      'খোঁড়াখুঁড়ি করে ফেলে রাখা',    'Unrepaired digging / utility cut'],
            ['open_manhole',     'খোলা ম্যানহোল বা ড্রেন',       'Open manhole or drain'],
            ['road_collapse',    'রাস্তা ধসে যাওয়া',            'Road collapse or erosion'],
            ['bridge_culvert',   'ভাঙা ব্রিজ বা কালভার্ট',       'Damaged bridge or culvert'],
            ['damaged_footpath', 'ভাঙা ফুটপাত',                'Damaged footpath'],
            ['unpaved_muddy',    'কাঁচা/কর্দমাক্ত রাস্তা',        'Unpaved or muddy road'],
            ['other',            'অন্যান্য',                    'Other'],
        ];

        foreach ($categories as $i => [$slug, $bn, $en]) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $slug],
                [
                    'parent_id' => $parentId,
                    'name_bn' => $bn,
                    'name_en' => $en,
                    'sort' => $i + 1,
                    'is_active' => true,
                    'is_reportable' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
