<?php

return [
    // Reasons written by the system itself.
    // Keys are the EXACT text stored in status_events.reason.
    'reasons' => [
        'Issue created from a community report.' => 'Issue created from a community report.',
        'Authority acknowledged by phone call'    => 'Authority acknowledged by phone call.',
        'Demo data.'                              => '[Demo] Sample data, not a real case.',
    ],

    // Written by DuplicateService. :id is the public id of the main issue.
    'duplicate_of' => 'Marked as duplicate of issue #:id.',
];