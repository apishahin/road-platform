<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Small, dependency-free QR encoder for the public install-link card.
 *
 * It emits a standards-compliant byte-mode QR code with low error correction.
 * Install URLs are short, so versions 1–10 cover normal production domains
 * while keeping the download/share card fast and fully offline.
 */
final class QrCodeSvg
{
    /** @var array<int, array{capacity: int, blocks: array<int, array{0: int, 1: int, 2: int}>, alignment: array<int, int>}> */
    private const VERSIONS = [
        1  => ['capacity' => 17,  'blocks' => [[1, 19, 7]],            'alignment' => []],
        2  => ['capacity' => 32,  'blocks' => [[1, 34, 10]],           'alignment' => [6, 18]],
        3  => ['capacity' => 53,  'blocks' => [[1, 55, 15]],           'alignment' => [6, 22]],
        4  => ['capacity' => 78,  'blocks' => [[1, 80, 20]],           'alignment' => [6, 26]],
        5  => ['capacity' => 106, 'blocks' => [[1, 108, 26]],          'alignment' => [6, 30]],
        6  => ['capacity' => 134, 'blocks' => [[2, 68, 18]],           'alignment' => [6, 34]],
        7  => ['capacity' => 154, 'blocks' => [[2, 78, 20]],           'alignment' => [6, 22, 38]],
        8  => ['capacity' => 192, 'blocks' => [[2, 97, 24]],           'alignment' => [6, 24, 42]],
        9  => ['capacity' => 230, 'blocks' => [[2, 116, 30]],          'alignment' => [6, 26, 46]],
        10 => ['capacity' => 271, 'blocks' => [[2, 68, 18], [2, 69, 18]], 'alignment' => [6, 28, 50]],
    ];

    /** @var array{0: array<int, int>, 1: array<int, int>}|null */
    private static ?array $gf = null;

    /** Return an accessible standalone SVG for a short URL or other ASCII text. */
    public static function svg(string $payload, string $title = 'Install app QR code'): string
    {
        $matrix = self::matrix($payload);
        $size = count($matrix);
        $path = '';

        foreach ($matrix as $row => $cells) {
            foreach ($cells as $column => $dark) {
                if ($dark) {
                    $path .= "M{$column} {$row}h1v1h-1z";
                }
            }
        }

        $edge = $size + 8; // Four quiet modules on every side.
        $label = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-4 -4 '.$edge.' '.$edge.'" role="img" aria-labelledby="install-qr-title" shape-rendering="crispEdges">'
            . '<title id="install-qr-title">'.$label.'</title>'
            . '<rect x="-4" y="-4" width="'.$edge.'" height="'.$edge.'" fill="#ffffff"/>'
            . '<path d="'.$path.'" fill="#0f172a"/>'
            . '</svg>';
    }

    /**
     * @return array<int, array<int, bool>> rows of dark/light QR modules
     */
    public static function matrix(string $payload): array
    {
        $version = self::versionFor($payload);
        $spec = self::VERSIONS[$version];
        $size = 17 + (4 * $version);
        /** @var array<int, array<int, bool|null>> $modules */
        $modules = array_fill(0, $size, array_fill(0, $size, null));

        self::finder($modules, 0, 0);
        self::finder($modules, $size - 7, 0);
        self::finder($modules, 0, $size - 7);

        foreach ($spec['alignment'] as $row) {
            foreach ($spec['alignment'] as $column) {
                // Centres already occupied by a finder pattern are skipped.
                if ($modules[$row][$column] === null) {
                    self::alignment($modules, $row, $column);
                }
            }
        }

        for ($i = 8; $i < $size - 8; $i++) {
            self::set($modules, 6, $i, ($i % 2) === 0);
            self::set($modules, $i, 6, ($i % 2) === 0);
        }

        self::reserveFormat($modules);
        if ($version >= 7) {
            self::reserveVersion($modules, $version);
        }

        // The fixed dark module belongs to every QR symbol.
        self::set($modules, $size - 8, 8, true);

        // Mask zero is simple and valid for any QR code: (row + column) is even.
        self::placeData($modules, self::interleavedCodewords($payload, $version), 0);
        self::writeFormat($modules, 0);
        if ($version >= 7) {
            self::writeVersion($modules, $version);
        }

        foreach ($modules as $row => $cells) {
            foreach ($cells as $column => $value) {
                // A complete symbol has no unset modules. Keeping this defensive
                // fill makes malformed input fail closed to a light module.
                $modules[$row][$column] = (bool) $value;
            }
        }

        /** @var array<int, array<int, bool>> $modules */
        return $modules;
    }

    private static function versionFor(string $payload): int
    {
        $bytes = strlen($payload);
        foreach (self::VERSIONS as $version => $spec) {
            if ($bytes <= $spec['capacity']) {
                return $version;
            }
        }

        throw new InvalidArgumentException('The QR payload is too long.');
    }

    /** @param array<int, array<int, bool|null>> $modules */
    private static function finder(array &$modules, int $top, int $left): void
    {
        $size = count($modules);
        for ($row = -1; $row <= 7; $row++) {
            for ($column = -1; $column <= 7; $column++) {
                $r = $top + $row;
                $c = $left + $column;
                if ($r < 0 || $c < 0 || $r >= $size || $c >= $size) {
                    continue;
                }

                $dark = $row >= 0 && $row <= 6 && $column >= 0 && $column <= 6
                    && ($row === 0 || $row === 6 || $column === 0 || $column === 6
                        || ($row >= 2 && $row <= 4 && $column >= 2 && $column <= 4));
                self::set($modules, $r, $c, $dark);
            }
        }
    }

    /** @param array<int, array<int, bool|null>> $modules */
    private static function alignment(array &$modules, int $centreRow, int $centreColumn): void
    {
        for ($row = -2; $row <= 2; $row++) {
            for ($column = -2; $column <= 2; $column++) {
                $outer = max(abs($row), abs($column)) === 2;
                self::set($modules, $centreRow + $row, $centreColumn + $column, $outer || ($row === 0 && $column === 0));
            }
        }
    }

    /** @param array<int, array<int, bool|null>> $modules */
    private static function reserveFormat(array &$modules): void
    {
        $size = count($modules);
        for ($i = 0; $i < 15; $i++) {
            if ($i < 6) {
                self::set($modules, $i, 8, false);
            } elseif ($i < 8) {
                self::set($modules, $i + 1, 8, false);
            } else {
                self::set($modules, $size - 15 + $i, 8, false);
            }

            if ($i < 8) {
                self::set($modules, 8, $size - $i - 1, false);
            } elseif ($i < 9) {
                self::set($modules, 8, 15 - $i, false);
            } else {
                self::set($modules, 8, 15 - $i - 1, false);
            }
        }
    }

    /** @param array<int, array<int, bool|null>> $modules */
    private static function reserveVersion(array &$modules, int $version): void
    {
        $size = count($modules);
        for ($i = 0; $i < 18; $i++) {
            self::set($modules, intdiv($i, 3), $size - 11 + ($i % 3), false);
            self::set($modules, $size - 11 + ($i % 3), intdiv($i, 3), false);
        }
    }

    /** @param array<int, array<int, bool|null>> $modules */
    private static function writeFormat(array &$modules, int $mask): void
    {
        // Error correction level L = binary 01. BCH(15,5), then the QR mask.
        $bits = self::formatBits((1 << 3) | $mask);
        $size = count($modules);
        for ($i = 0; $i < 15; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            if ($i < 6) {
                self::set($modules, $i, 8, $dark);
            } elseif ($i < 8) {
                self::set($modules, $i + 1, 8, $dark);
            } else {
                self::set($modules, $size - 15 + $i, 8, $dark);
            }

            if ($i < 8) {
                self::set($modules, 8, $size - $i - 1, $dark);
            } elseif ($i < 9) {
                self::set($modules, 8, 15 - $i, $dark);
            } else {
                self::set($modules, 8, 15 - $i - 1, $dark);
            }
        }
    }

    /** @param array<int, array<int, bool|null>> $modules */
    private static function writeVersion(array &$modules, int $version): void
    {
        $bits = self::versionBits($version);
        $size = count($modules);
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            self::set($modules, intdiv($i, 3), $size - 11 + ($i % 3), $dark);
            self::set($modules, $size - 11 + ($i % 3), intdiv($i, 3), $dark);
        }
    }

    /** @param array<int, array<int, bool|null>> $modules @param array<int, int> $codewords */
    private static function placeData(array &$modules, array $codewords, int $mask): void
    {
        $size = count($modules);
        $bitIndex = 0;
        $row = $size - 1;
        $direction = -1;

        for ($column = $size - 1; $column > 0; $column -= 2) {
            if ($column === 6) {
                $column--;
            }

            while (true) {
                for ($offset = 0; $offset < 2; $offset++) {
                    $c = $column - $offset;
                    if ($modules[$row][$c] !== null) {
                        continue;
                    }

                    $dark = false;
                    if ($bitIndex < count($codewords) * 8) {
                        $dark = (($codewords[intdiv($bitIndex, 8)] >> (7 - ($bitIndex % 8))) & 1) === 1;
                        $bitIndex++;
                    }
                    if (self::masked($mask, $row, $c)) {
                        $dark = ! $dark;
                    }
                    self::set($modules, $row, $c, $dark);
                }

                $row += $direction;
                if ($row < 0 || $row >= $size) {
                    $row -= $direction;
                    $direction = -$direction;
                    break;
                }
            }
        }
    }

    private static function masked(int $mask, int $row, int $column): bool
    {
        return match ($mask) {
            0 => (($row + $column) % 2) === 0,
            default => false,
        };
    }

    /** @return array<int, int> */
    private static function interleavedCodewords(string $payload, int $version): array
    {
        $spec = self::VERSIONS[$version];
        $dataLength = 0;
        foreach ($spec['blocks'] as [$count, $bytes]) {
            $dataLength += $count * $bytes;
        }

        $data = self::dataCodewords($payload, $version, $dataLength);
        $blocks = [];
        $offset = 0;
        foreach ($spec['blocks'] as [$count, $bytes, $eccBytes]) {
            for ($i = 0; $i < $count; $i++) {
                $chunk = array_slice($data, $offset, $bytes);
                $offset += $bytes;
                $blocks[] = ['data' => $chunk, 'ecc' => self::reedSolomon($chunk, $eccBytes)];
            }
        }

        $result = [];
        $dataMax = max(array_map(fn (array $block) => count($block['data']), $blocks));
        for ($i = 0; $i < $dataMax; $i++) {
            foreach ($blocks as $block) {
                if (isset($block['data'][$i])) {
                    $result[] = $block['data'][$i];
                }
            }
        }

        $eccMax = max(array_map(fn (array $block) => count($block['ecc']), $blocks));
        for ($i = 0; $i < $eccMax; $i++) {
            foreach ($blocks as $block) {
                if (isset($block['ecc'][$i])) {
                    $result[] = $block['ecc'][$i];
                }
            }
        }

        return $result;
    }

    /** @return array<int, int> */
    private static function dataCodewords(string $payload, int $version, int $capacity): array
    {
        $bits = [];
        self::appendBits($bits, 0b0100, 4); // byte mode
        self::appendBits($bits, strlen($payload), $version < 10 ? 8 : 16);
        foreach (unpack('C*', $payload) ?: [] as $byte) {
            self::appendBits($bits, $byte, 8);
        }

        $capacityBits = $capacity * 8;
        for ($i = 0; $i < min(4, $capacityBits - count($bits)); $i++) {
            $bits[] = false;
        }
        while ((count($bits) % 8) !== 0) {
            $bits[] = false;
        }

        $bytes = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $value = 0;
            for ($bit = 0; $bit < 8; $bit++) {
                $value = ($value << 1) | ($bits[$i + $bit] ? 1 : 0);
            }
            $bytes[] = $value;
        }

        $pad = [0xec, 0x11];
        $padIndex = 0;
        while (count($bytes) < $capacity) {
            $bytes[] = $pad[$padIndex++ % 2];
        }

        return $bytes;
    }

    /** @param array<int, bool> $bits */
    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = (($value >> $i) & 1) === 1;
        }
    }

    /** @param array<int, int> $data @return array<int, int> */
    private static function reedSolomon(array $data, int $degree): array
    {
        $generator = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $index => $coefficient) {
                $next[$index] ^= $coefficient;
                $next[$index + 1] ^= self::multiply($coefficient, self::exp($i));
            }
            $generator = $next;
        }

        $result = array_fill(0, $degree, 0);
        foreach ($data as $value) {
            $factor = $value ^ $result[0];
            array_shift($result);
            $result[] = 0;
            foreach ($result as $index => $ignored) {
                $result[$index] ^= self::multiply($generator[$index + 1], $factor);
            }
        }

        return $result;
    }

    private static function multiply(int $left, int $right): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        return self::exp(self::log($left) + self::log($right));
    }

    private static function exp(int $power): int
    {
        self::gf();

        return self::$gf[0][$power % 255];
    }

    private static function log(int $value): int
    {
        self::gf();

        return self::$gf[1][$value];
    }

    private static function gf(): void
    {
        if (self::$gf !== null) {
            return;
        }

        $exp = array_fill(0, 255, 0);
        $log = array_fill(0, 256, 0);
        $value = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $value;
            $log[$value] = $i;
            $value <<= 1;
            if ($value & 0x100) {
                $value ^= 0x11d;
            }
        }
        self::$gf = [$exp, $log];
    }

    private static function formatBits(int $data): int
    {
        $remainder = $data << 10;
        while (self::bitLength($remainder) >= self::bitLength(0x537)) {
            $remainder ^= 0x537 << (self::bitLength($remainder) - self::bitLength(0x537));
        }

        return (($data << 10) | $remainder) ^ 0x5412;
    }

    private static function versionBits(int $version): int
    {
        $remainder = $version << 12;
        while (self::bitLength($remainder) >= self::bitLength(0x1f25)) {
            $remainder ^= 0x1f25 << (self::bitLength($remainder) - self::bitLength(0x1f25));
        }

        return ($version << 12) | $remainder;
    }

    private static function bitLength(int $value): int
    {
        $length = 0;
        while ($value > 0) {
            $length++;
            $value >>= 1;
        }

        return $length;
    }

    /** @param array<int, array<int, bool|null>> $modules */
    private static function set(array &$modules, int $row, int $column, bool $dark): void
    {
        $modules[$row][$column] = $dark;
    }
}
