<?php

namespace App\Support;

class Phone
{
    /** Convert Bangla digits to English digits. */
    public static function digits(string $value): string
    {
        return strtr($value, '০১২৩৪৫৬৭৮৯', '0123456789');
    }

    /** Returns +8801XXXXXXXXX, or null if it is not a valid Bangladesh mobile number. */
    public static function normalize(string $input): ?string
    {
        $d = preg_replace('/\D/', '', self::digits($input));

        if (str_starts_with($d, '8801') && strlen($d) === 13) {
            // already full form
        } elseif (str_starts_with($d, '01') && strlen($d) === 11) {
            $d = '88' . $d;
        } elseif (str_starts_with($d, '1') && strlen($d) === 10) {
            $d = '880' . $d;
        } else {
            return null;
        }

        return preg_match('/^8801[3-9]\d{8}$/', $d) ? '+' . $d : null;
    }

    /** +88017•••••••89 (for showing on screen). */
    public static function mask(string $phone): string
    {
        return substr($phone, 0, 6) . str_repeat('•', max(strlen($phone) - 8, 0)) . substr($phone, -2);
    }
}