<?php

namespace App\Services;

use App\Models\Evidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EvidenceBlurService
{
    /**
     * Cover parts of a PENDING photo with black boxes, then delete the original copy.
     * $rects = [['x'=>0..1,'y'=>0..1,'w'=>0..1,'h'=>0..1], ...] (fractions of the image).
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
                if ($x2 >= $x1 && $y2 >= $y1) {
                    imagefilledrectangle($im, $x1, $y1, $x2, $y2, $black);
                }
            }

            ob_start();
            imagejpeg($im, null, 85);
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
}