<?php

return [
    // Reasons written by the system itself.
    // Keys are the EXACT text stored in status_events.reason.
    // Stored text is never edited (append-only); translation happens at display time only.
    'reasons' => [
        'Issue created from a community report.' => 'নাগরিকের রিপোর্ট থেকে সমস্যাটি তৈরি করা হয়েছে।',
        'Authority acknowledged by phone call'    => 'কর্তৃপক্ষ ফোনে সমস্যাটি স্বীকার করেছে।',
        'Demo data.'                              => '[ডেমো] নমুনা তথ্য, আসল ঘটনা নয়।',
    ],

    // Written by DuplicateService. :id is the public id of the main issue.
    'duplicate_of' => 'সমস্যা #:id-এর ডুপ্লিকেট হিসেবে চিহ্নিত করা হয়েছে।',
];