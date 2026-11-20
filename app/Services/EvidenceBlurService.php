<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EvidenceBlurService
{
    /** The longest side of a blurred area is shrunk to this many pixels (nothing readable is left). */
    private const BLUR_DETAIL = 12;

    /**
     * Cover parts of a PENDING photo, then delete the original copy.
     * $rects = [['x'=>0..1,'y'=>0..1,'w'=>0..1,'h'=>0..1,'m'=>'blur'|'black'], ...] (fractions of the image).
     * Returns false when the photo is not pending or is hidden.
     */
    public function blur(Evidence $evidence, User $moderator, array $rects): bool
    {
        return DB::transaction(function () use ($evidence, $rects) {
            $ev = Evidence::whereKey($evidence->id)->lockForUpdate()->firstOrFail();
            if ($ev->moderation !== 'pending' || $ev->hidden_at) {
                return false;
            }

            $disk = Storage::disk($ev->private_disk ?: \App\Support\StorageSettings::privateDisk());
            $im = @imagecreatefromstring($disk->get($ev->public_path));
            if (! $im) {
                throw new RuntimeException('Image could not be read.');
            }

            $w = imagesx($im);
            $h = imagesy($im);
            $black = imagecolorallocate($im, 0, 0, 0);

            foreach ($rects as $r) {
                $x1 = max(0, (int) floor($r['x'] * $w));
                $y1 = max(0, (int) floor($r['y'] * $h));
                $x2 = min($w - 1, (int) ceil(($r['x'] + $r['w']) * $w) - 1);
                $y2 = min($h - 1, (int) ceil(($r['y'] + $r['h']) * $h) - 1);
                if ($x2 < $x1 || $y2 < $y1) {
                    continue;
                }

                if (($r['m'] ?? 'blur') === 'black') {
                    imagefilledrectangle($im, $x1, $y1, $x2, $y2, $black);
                } else {
                    $this->blurRegion($im, $x1, $y1, $x2, $y2);
                }
            }

            ob_start();
            if (str_ends_with((string) $ev->public_path, '.webp') && function_exists('imagewebp')) {
                imagewebp($im, null, 88);
            } else {
                imagejpeg($im, null, 88);
            }
            $out = ob_get_clean();
            imagedestroy($im);

            if (! is_string($out) || $out === '') {
                throw new RuntimeException('Image could not be saved.');
            }

            // 1) save the covered copy, 2) delete the clear original, 3) remember it is gone
            $disk->put($ev->public_path, $out);
            if ($ev->private_path) {
                $disk->delete($ev->private_path);
            }
            $ev->forceFill(['private_path' => null])->save();

            return true;
        });
    }

    /**
     * Soft blur that cannot be undone: shrink the area to a tiny picture (detail is thrown away),
     * stretch it back smoothly, then smooth it a little more.
     */
    private function blurRegion($im, int $x1, int $y1, int $x2, int $y2): void
    {
        $w = $x2 - $x1 + 1;
        $h = $y2 - $y1 + 1;

        $scale = self::BLUR_DETAIL / max($w, $h);
        $sw = max(1, (int) round($w * $scale));
        $sh = max(1, (int) round($h * $scale));

        $small = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($small, $im, 0, 0, $x1, $y1, $sw, $sh, $w, $h);

        $big = imagecreatetruecolor($w, $h);
        imagecopyresampled($big, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($small);

        for ($i = 0; $i < 3; $i++) {
            imagefilter($big, IMG_FILTER_GAUSSIAN_BLUR);
        }

        imagecopy($im, $big, $x1, $y1, 0, 0, $w, $h);
        imagedestroy($big);
    }
}