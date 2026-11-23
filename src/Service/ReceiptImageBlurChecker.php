<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Rejects very blurry receipt photos using variance of the Laplacian on a resized grayscale image.
 * If GD is missing or the image cannot be decoded, the check is skipped (does not block upload).
 */
final class ReceiptImageBlurChecker
{
    private const MAX_EDGE_PX = 512;

    public function __construct(
        private readonly float $minLaplacianVariance,
    ) {
    }

    public function isTooBlurry(UploadedFile $file): bool
    {
        [$tooBlurry] = $this->measure($file);

        return $tooBlurry;
    }

    /**
     * @return array{0: bool, 1: float|null} [tooBlurry, laplacianVarianceOrNull]
     */
    private function measure(UploadedFile $file): array
    {
        if (!\extension_loaded('gd')) {
            return [false, null];
        }

        $path = $file->getPathname();
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return [false, null];
        }

        $im = @imagecreatefromstring($raw);
        if ($im === false) {
            return [false, null];
        }

        try {
            $w = imagesx($im);
            $h = imagesy($im);
            if ($w < 2 || $h < 2) {


                return [true, 0.0];
            }

            $scale = min(self::MAX_EDGE_PX / $w, self::MAX_EDGE_PX / $h, 1.0);
            $nw = max(2, (int) round($w * $scale));
            $nh = max(2, (int) round($h * $scale));

            $small = imagecreatetruecolor($nw, $nh);
            if ($small === false) {


                return [false, null];
            }
            imagecopyresampled($small, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);

            /** @var list<list<int>> $gray */
            $gray = [];
            for ($y = 0; $y < $nh; ++$y) {
                $row = [];
                for ($x = 0; $x < $nw; ++$x) {
                    $rgb = imagecolorat($small, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    $row[] = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                }
                $gray[] = $row;
            }

            $responses = [];
            for ($y = 1; $y < $nh - 1; ++$y) {
                for ($x = 1; $x < $nw - 1; ++$x) {
                    $l = $gray[$y - 1][$x] + $gray[$y + 1][$x]
                        + $gray[$y][$x - 1] + $gray[$y][$x + 1]
                        - 4 * $gray[$y][$x];
                    $responses[] = (float) $l;
                }
            }

            if ($responses === []) {
                return [false, null];
            }

            $mean = array_sum($responses) / \count($responses);
            $var = 0.0;
            foreach ($responses as $v) {
                $var += ($v - $mean) ** 2;
            }
            $var /= \count($responses);

            return [$var < $this->minLaplacianVariance, $var];
        } catch (\Throwable) {
            return [false, null];
        }
    }
}
