<?php

namespace App\Services\Dau;

class DauComparisonChartRenderer
{
    /**
     * Helper to convert Hex to RGB array.
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }

    /**
     * Helper to find a clean human ceiling for the Y-axis.
     */
    private static function getNiceCeiling(float $value): float
    {
        if ($value <= 0) return 10;
        $exp = floor(log10($value));
        $fraction = $value / pow(10, $exp);

        if ($fraction <= 1.2) {
            $nice = 1.2;
        } elseif ($fraction <= 1.5) {
            $nice = 1.5;
        } elseif ($fraction <= 2.0) {
            $nice = 2.0;
        } elseif ($fraction <= 2.5) {
            $nice = 2.5;
        } elseif ($fraction <= 5.0) {
            $nice = 5.0;
        } elseif ($fraction <= 7.5) {
            $nice = 7.5;
        } else {
            $nice = 10.0;
        }

        return $nice * pow(10, $exp);
    }

    /**
     * Format number concisely (e.g. 1.2M, 45k, 500).
     */
    private static function formatShortNumber(float $v): string
    {
        if ($v >= 1000000000) {
            return round($v / 1000000000, 1) . 'B';
        }
        if ($v >= 1000000) {
            return round($v / 1000000, 1) . 'M';
        }
        if ($v >= 1000) {
            return round($v / 1000, 1) . 'k';
        }
        return number_format($v);
    }

    /**
     * Render a high-resolution retina raster PNG chart for trend line with data points and baseline marker.
     * Generates a 2x scaled image for razor-sharp rendering inside PDF.
     *
     * @param array $points Array of points ['short_label' => ..., 'value' => ..., 'is_baseline' => ...]
     * @param string $unit Metric unit (Pax, Movements, Kg)
     * @param string $strokeHex Hex color code
     * @param string|null $baselineKey Baseline key identifier
     * @param string $title Metric title
     * @param int $w Display width in px
     * @param int $h Display height in px
     * @return string Data URI (data:image/png;base64,...)
     */
    public static function renderTrendLineSvg(
        array $points,
        string $unit,
        string $strokeHex = '#2563eb',
        ?string $baselineKey = null,
        string $title = '',
        int $w = 680,
        int $h = 145
    ): string {
        $scale = 2; // 2x Retina resolution
        $width = $w * $scale;
        $height = $h * $scale;

        $im = imagecreatetruecolor($width, $height);
        if (function_exists('imageantialias')) {
            @imageantialias($im, true);
        }

        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $width, $height, $white);

        $padL = 65 * $scale;
        $padR = 40 * $scale;
        $padT = 25 * $scale;
        $padB = 30 * $scale;

        $chartW = $width - $padL - $padR;
        $chartH = $height - $padT - $padB;

        $vals = array_map(fn($p) => (float)($p['value'] ?? 0), $points);
        $maxVal = count($vals) ? max($vals) : 1;
        if ($maxVal <= 0) $maxVal = 1;

        $maxTick = self::getNiceCeiling($maxVal);

        $gridColor = imagecolorallocate($im, 226, 232, 240);
        $textDark = imagecolorallocate($im, 15, 23, 42);
        $textMuted = imagecolorallocate($im, 100, 116, 139);

        // Y-axis grid lines and labels
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $chartH - ($i / 4) * $chartH;
            $tv = ($maxTick / 4) * $i;
            $lbl = self::formatShortNumber($tv);

            // Subtle dashed line
            for ($gx = $padL; $gx < $padL + $chartW; $gx += 8 * $scale) {
                imageline($im, (int)$gx, (int)$y, (int)min($gx + 4 * $scale, $padL + $chartW), (int)$y, $gridColor);
            }
            $lblX = (int)($padL - (strlen($lbl) * 7 * $scale) - 4 * $scale);
            $lblY = (int)($y - 6 * $scale);
            imagestring($im, 2, max(2, $lblX), max(0, $lblY), $lbl, $textMuted);
        }

        $n = count($points);
        $coords = [];
        for ($i = 0; $i < $n; $i++) {
            $p = $points[$i];
            $v = (float)($p['value'] ?? 0);
            $x = $n === 1 ? ($padL + $chartW / 2) : ($padL + ($i / ($n - 1)) * $chartW);
            $y = $padT + $chartH - ($v / $maxTick) * $chartH;
            $coords[] = ['x' => (int)$x, 'y' => (int)$y, 'p' => $p, 'v' => $v];
        }

        // Connecting trend line
        [$sr, $sg, $sb] = self::hexToRgb($strokeHex);
        $lineColor = imagecolorallocate($im, $sr, $sg, $sb);
        imagesetthickness($im, 3 * $scale);
        for ($i = 0; $i < count($coords) - 1; $i++) {
            imageline($im, $coords[$i]['x'], $coords[$i]['y'], $coords[$i+1]['x'], $coords[$i+1]['y'], $lineColor);
        }

        // Distinct data points and baseline marker
        $amber = imagecolorallocate($im, 245, 158, 11);
        $amberLight = imagecolorallocate($im, 254, 243, 199);

        foreach ($coords as $c) {
            $x = $c['x'];
            $y = $c['y'];
            $p = $c['p'];
            $isBase = !empty($p['is_baseline']) || ($baselineKey && (($p['key'] ?? '') === $baselineKey || ($p['short_label'] ?? '') === $baselineKey));

            if ($isBase) {
                // Outer gold halo
                imagefilledellipse($im, $x, $y, 16 * $scale, 16 * $scale, $amberLight);
                imagesetthickness($im, 2 * $scale);
                imageellipse($im, $x, $y, 16 * $scale, 16 * $scale, $amber);
                imagefilledellipse($im, $x, $y, 8 * $scale, 8 * $scale, $amber);
            } else {
                imagefilledellipse($im, $x, $y, 10 * $scale, 10 * $scale, $white);
                imagesetthickness($im, 2 * $scale);
                imageellipse($im, $x, $y, 10 * $scale, 10 * $scale, $lineColor);
                imagefilledellipse($im, $x, $y, 6 * $scale, 6 * $scale, $lineColor);
            }

            // Numeric value label above point
            $valFmt = number_format($c['v']);
            $valX = (int)($x - (strlen($valFmt) * 3.5 * $scale));
            $valY = (int)($y - 16 * $scale);
            imagestring($im, 2, max(2, $valX), max(0, $valY), $valFmt, $textDark);

            // X-axis period label
            $xl = $p['short_label'] ?? $p['label'] ?? '';
            $xlX = (int)($x - (strlen($xl) * 4 * $scale));
            $xlY = (int)($height - 18 * $scale);
            imagestring($im, 3, max(2, $xlX), max(0, $xlY), $xl, $textDark);
        }

        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Render a high-resolution retina raster PNG chart for historical movement bar charts.
     * Supports grouped bars (Domestic vs International) or single series.
     *
     * @param array $periods Array of periods
     * @param array $datasets Array of ['label' => ..., 'color' => ..., 'values' => [...]]
     * @param string $unit Metric unit (Pax, Movements, Kg)
     * @param string $title Metric title
     * @param int $w Display width in px
     * @param int $h Display height in px
     * @return string Data URI (data:image/png;base64,...)
     */
    public static function renderHistoricalBarSvg(
        array $periods,
        array $datasets,
        string $unit,
        string $title = '',
        int $w = 680,
        int $h = 145
    ): string {
        $scale = 2; // 2x Retina resolution
        $width = $w * $scale;
        $height = $h * $scale;

        $im = imagecreatetruecolor($width, $height);
        if (function_exists('imageantialias')) {
            @imageantialias($im, true);
        }

        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $width, $height, $white);

        $padL = 65 * $scale;
        $padR = 40 * $scale;
        $padT = 25 * $scale;
        $padB = 30 * $scale;

        $chartW = $width - $padL - $padR;
        $chartH = $height - $padT - $padB;

        // Calculate max value across all datasets
        $maxVal = 0;
        foreach ($datasets as $ds) {
            foreach ($ds['values'] as $v) {
                if ($v > $maxVal) $maxVal = (float)$v;
            }
        }
        if ($maxVal <= 0) $maxVal = 1;
        $maxTick = self::getNiceCeiling($maxVal);

        $gridColor = imagecolorallocate($im, 226, 232, 240);
        $textDark = imagecolorallocate($im, 15, 23, 42);
        $textMuted = imagecolorallocate($im, 100, 116, 139);

        // Y-axis grid lines and labels
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $chartH - ($i / 4) * $chartH;
            $tv = ($maxTick / 4) * $i;
            $lbl = self::formatShortNumber($tv);

            // Subtle dashed line
            for ($gx = $padL; $gx < $padL + $chartW; $gx += 8 * $scale) {
                imageline($im, (int)$gx, (int)$y, (int)min($gx + 4 * $scale, $padL + $chartW), (int)$y, $gridColor);
            }
            $lblX = (int)($padL - (strlen($lbl) * 7 * $scale) - 4 * $scale);
            $lblY = (int)($y - 6 * $scale);
            imagestring($im, 2, max(2, $lblX), max(0, $lblY), $lbl, $textMuted);
        }

        $numPeriods = max(1, count($periods));
        $numDatasets = max(1, count($datasets));
        $groupSlotW = $chartW / $numPeriods;
        $barMaxW = 32 * $scale;
        $barW = min($barMaxW, ($groupSlotW * 0.7) / $numDatasets);
        $totalGroupBarsW = $barW * $numDatasets;

        // Allocate dataset colors
        $dsColors = [];
        foreach ($datasets as $dIdx => $ds) {
            [$r, $g, $b] = self::hexToRgb($ds['color'] ?? ($dIdx === 0 ? '#3b82f6' : '#8b5cf6'));
            $dsColors[$dIdx] = imagecolorallocate($im, $r, $g, $b);
        }

        for ($pIdx = 0; $pIdx < $numPeriods; $pIdx++) {
            $p = $periods[$pIdx];
            $slotCenterX = $padL + ($pIdx + 0.5) * $groupSlotW;
            $groupStartX = $slotCenterX - ($totalGroupBarsW / 2);

            foreach ($datasets as $dIdx => $ds) {
                $val = (float)($ds['values'][$pIdx] ?? 0);
                $barH = ($val / $maxTick) * $chartH;
                if ($barH < 0) $barH = 0;

                $bx1 = (int)($groupStartX + ($dIdx * $barW));
                $bx2 = (int)($bx1 + $barW - (2 * $scale));
                $by2 = (int)($padT + $chartH);
                $by1 = (int)($by2 - $barH);

                if ($barH > 0) {
                    imagefilledrectangle($im, $bx1, $by1, $bx2, $by2, $dsColors[$dIdx]);

                    // Short value label above bar
                    $valLbl = self::formatShortNumber($val);
                    $valLblX = (int)(($bx1 + $bx2) / 2 - (strlen($valLbl) * 3 * $scale));
                    $valLblY = (int)($by1 - 12 * $scale);
                    imagestring($im, 1, max(2, $valLblX), max(0, $valLblY), $valLbl, $textDark);
                } else {
                    // Baseline notch
                    $gray = imagecolorallocate($im, 203, 213, 225);
                    imagefilledrectangle($im, $bx1, $by2 - 2 * $scale, $bx2, $by2, $gray);
                }
            }

            // X-axis period label
            $xl = $p['short_label'] ?? $p['label'] ?? '';
            $xlX = (int)($slotCenterX - (strlen($xl) * 4 * $scale));
            $xlY = (int)($height - 18 * $scale);
            imagestring($im, 3, max(2, $xlX), max(0, $xlY), $xl, $textDark);
        }

        // Legend at top right if multiple datasets
        if ($numDatasets > 1) {
            $legX = $width - $padR;
            $legY = (int)(12 * $scale);
            foreach (array_reverse($datasets, true) as $dIdx => $ds) {
                $lbl = $ds['label'];
                $itemW = strlen($lbl) * 7 * $scale + 22 * $scale;
                $legX -= $itemW;

                imagefilledrectangle($im, (int)$legX, (int)($legY - 5 * $scale), (int)($legX + 10 * $scale), (int)($legY + 5 * $scale), $dsColors[$dIdx]);
                imagestring($im, 2, (int)($legX + 14 * $scale), (int)($legY - 6 * $scale), $lbl, $textDark);
            }
        }

        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
