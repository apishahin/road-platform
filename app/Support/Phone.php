<?php

namespace App\Support;

class Phone
{
    /** Convert Bangla digits to English digits. */
    public static function digits(string $value): string
    {
        return strtr($value, [
            "\u{09E6}" => '0', "\u{09E7}" => '1', "\u{09E8}" => '2', "\u{09E9}" => '3', "\u{09EA}" => '4',
            "\u{09EB}" => '5', "\u{09EC}" => '6', "\u{09ED}" => '7', "\u{09EE}" => '8', "\u{09EF}" => '9',
        ]);
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