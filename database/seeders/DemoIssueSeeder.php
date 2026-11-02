<?php

namespace Database\Seeders;

use App\Models\{Category, Issue, StatusEvent, User};
use Illuminate\Database\Seeder;

class DemoIssueSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $cat   = Category::pluck('id', 'slug');

        // [title, lat, lng, category, progress, verification, road, daysAgo, reports]
        $rows = [
            ['[ডেমো] বড় গর্ত',               23.7561, 90.3872, 'pothole',       'reported',            'unverified',          'ডেমো রোড ১', 2, 3],
            ['[ডেমো] জলাবদ্ধতা',             23.7781, 90.4056, 'waterlogging',  'acknowledged',        'community_confirmed', 'ডেমো রোড ২', 9, 5],
            ['[ডেমো] রাস্তা ধসে পড়েছে',       23.8759, 90.3795, 'road_collapse', 'planned',             'reviewed',            'ডেমো রোড ৩', 15, 4],
            ['[ডেমো] খোঁড়াখুঁড়ি ফেলে রাখা',   23.7104, 90.4344, 'utility_cut',   'work_started',        'reviewed',            'ডেমো রোড ৪', 20, 6],
            ['[ডেমো] ভাঙা রাস্তা',            23.7587, 90.3589, 'damaged_road',  'completion_reported', 'unverified',          'ডেমো রোড ৫', 30, 2],
            ['[ডেমো] মেরামত হয়েছে',          23.7806, 90.4258, 'pothole',       'repaired',            'verified',            'ডেমো রোড ৬', 45, 7],
            ['[ডেমো] খোলা ম্যানহোল',          23.7126, 90.4128, 'open_manhole',  'reported',            'unverified',          'ডেমো রোড ৭', 1, 1],
        ];

        foreach ($rows as [$title, $lat, $lng, $slug, $progress, $verification, $road, $days, $reports]) {
            if (Issue::where('title', $title)->exists()) {
                continue;
            }

            $created = now()->subDays($days);

            $issue = Issue::create([
                'title'               => $title,
                'category_id'         => $cat[$slug],
                'lat'                 => $lat,
                'lng'                 => $lng,
                'road_name'           => $road,
                'progress_status'     => $progress,
                'verification_status' => $verification,
                'disposition'         => 'active',
                'reports_count'       => $reports,
                'first_reported_at'   => $created,
                'last_activity_at'    => now()->subDays(max(0, $days - 3)),
            ]);

            // Demo data is written directly, bypassing the rules in IssueStatusService.
            StatusEvent::create([
                'issue_id'    => $issue->id,
                'dimension'   => 'progress',
                'from_value'  => null,
                'to_value'    => 'reported',
                'actor_id'    => $admin->id,
                'actor_type'  => 'system',
                'source_type' => 'platform',
                'reason'      => 'Demo data.',
                'created_at'  => $created,
            ]);
        }
    }
}