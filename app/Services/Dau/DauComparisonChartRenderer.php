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
     * Render a high-resolution retina raster PNG dual-axis combo chart (Bar actuals + Line growth %).
     * Generates a 2x scaled image for razor-sharp rendering inside PDF.
     *
     * @param array $points Array of points ['short_label' => ..., 'value' => ..., 'growth_pct' => ..., 'growth_fmt' => ..., 'is_baseline' => ...]
     * @param string $unit Metric unit (Pax, Movements, Kg)
     * @param string $barHex Hex color code for Actual Volume bars
     * @param string $lineHex Hex color code for Period Growth line
     * @param string|null $baselineKey Baseline key identifier
     * @param string $title Metric title
     * @param int $w Display width in px
     * @param int $h Display height in px
     * @return string Data URI (data:image/png;base64,...)
     */
    public static function renderOperationalComboChartPng(
        array $points,
        string $unit,
        string $barHex = '#2563eb',
        string $lineHex = '#f59e0b',
        ?string $baselineKey = null,
        string $title = '',
        int $w = 680,
        int $h = 150
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

        $padL = 70 * $scale; // Left Y-axis (Actuals)
        $padR = 55 * $scale; // Right Y-axis (Growth %)
        $padT = 28 * $scale;
        $padB = 30 * $scale;

        $chartW = $width - $padL - $padR;
        $chartH = $height - $padT - $padB;

        // 1. Left Y-Axis Scale (Actual Metric Value)
        $vals = array_map(fn($p) => (float)($p['value'] ?? 0), $points);
        $maxVal = count($vals) ? max($vals) : 1;
        if ($maxVal <= 0) $maxVal = 1;
        $maxTick = self::getNiceCeiling($maxVal);

        // 2. Right Y-Axis Scale (Period-over-period Growth %)
        $growths = [];
        foreach ($points as $p) {
            if (isset($p['growth_pct']) && $p['growth_pct'] !== null && is_numeric($p['growth_pct'])) {
                $growths[] = (float)$p['growth_pct'];
            }
        }

        if (empty($growths)) {
            $growthMin = -20.0;
            $growthMax = 100.0;
        } else {
            $minG = min($growths);
            $maxG = max($growths);
            $bottom = ($minG < 0) ? floor($minG / 20.0) * 20.0 : 0.0;
            $top = ($maxG > 0) ? ceil($maxG / 20.0) * 20.0 : 20.0;
            if ($top - $bottom < 40.0) {
                $top = $bottom + 40.0;
            }
            $growthMin = $bottom;
            $growthMax = $top;
        }
        $growthRange = ($growthMax - $growthMin) ?: 1.0;

        $gridColor = imagecolorallocate($im, 226, 232, 240);
        $textDark = imagecolorallocate($im, 15, 23, 42);
        $textMuted = imagecolorallocate($im, 100, 116, 139);

        [$br, $bg, $bb] = self::hexToRgb($barHex);
        $barColor = imagecolorallocate($im, $br, $bg, $bb);

        [$lr, $lg, $lb] = self::hexToRgb($lineHex);
        $lineColor = imagecolorallocate($im, $lr, $lg, $lb);

        $amber = imagecolorallocate($im, 245, 158, 11);
        $amberLight = imagecolorallocate($im, 254, 243, 199);

        // Y-axis grid lines and Dual Axis Ticks
        for ($i = 0; $i <= 4; $i++) {
            $y = $padT + $chartH - ($i / 4) * $chartH;

            // Horizontal dashed grid line
            for ($gx = $padL; $gx < $padL + $chartW; $gx += 8 * $scale) {
                imageline($im, (int)$gx, (int)$y, (int)min($gx + 4 * $scale, $padL + $chartW), (int)$y, $gridColor);
            }

            // Left Y-Axis Tick (Actual value)
            $tv = ($maxTick / 4) * $i;
            $lblLeft = self::formatShortNumber($tv);
            $lblLeftX = (int)($padL - (strlen($lblLeft) * 7 * $scale) - 4 * $scale);
            $lblLeftY = (int)($y - 6 * $scale);
            imagestring($im, 2, max(2, $lblLeftX), max(0, $lblLeftY), $lblLeft, $textMuted);

            // Right Y-Axis Tick (Growth %)
            $gv = $growthMin + ($growthRange / 4) * $i;
            $lblRight = ($gv > 0 ? '+' : '') . round($gv) . '%';
            $lblRightX = (int)($padL + $chartW + 6 * $scale);
            $lblRightY = (int)($y - 6 * $scale);
            imagestring($im, 2, $lblRightX, max(0, $lblRightY), $lblRight, $lineColor);
        }

        // Axis Titles
        imagestring($im, 2, (int)(10 * $scale), (int)(10 * $scale), $unit, $textMuted);
        $rightTitle = 'Growth %';
        $rtX = (int)($width - (strlen($rightTitle) * 7 * $scale) - 10 * $scale);
        imagestring($im, 2, $rtX, (int)(10 * $scale), $rightTitle, $lineColor);

        // Legend at top center-right
        $legActual = 'Actual ' . ($unit === 'Pax' ? 'Passenger' : ($unit === 'Movements' ? 'Aircraft' : 'Cargo'));
        $legGrowth = 'Period Growth %';
        $legX = (int)($padL + $chartW / 2 - 40 * $scale);
        $legY = (int)(10 * $scale);

        // Actual movement box
        imagefilledrectangle($im, $legX, $legY + 2 * $scale, $legX + 8 * $scale, $legY + 8 * $scale, $barColor);
        imagestring($im, 2, $legX + 11 * $scale, $legY, $legActual, $textDark);

        // Growth line dot
        $legX2 = $legX + strlen($legActual) * 7 * $scale + 24 * $scale;
        imageline($im, $legX2, $legY + 5 * $scale, $legX2 + 12 * $scale, $legY + 5 * $scale, $lineColor);
        imagefilledellipse($im, $legX2 + 6 * $scale, $legY + 5 * $scale, 6 * $scale, 6 * $scale, $lineColor);
        imagestring($im, 2, $legX2 + 16 * $scale, $legY, $legGrowth, $textDark);

        $n = max(1, count($points));
        $slotW = $chartW / $n;
        $barMaxW = 34 * $scale;
        $barW = min($barMaxW, $slotW * 0.42);

        $lineCoords = [];

        // 3. Draw Bar Layer & collect Growth Line points
        for ($i = 0; $i < $n; $i++) {
            $p = $points[$i];
            $slotCenterX = $padL + ($i + 0.5) * $slotW;
            $v = (float)($p['value'] ?? 0);
            $isBase = !empty($p['is_baseline']) || ($baselineKey && (($p['key'] ?? '') === $baselineKey || ($p['short_label'] ?? '') === $baselineKey));

            // Bar dimensions
            $barH = ($v / $maxTick) * $chartH;
            if ($barH < 0) $barH = 0;
            $bx1 = (int)($slotCenterX - $barW / 2);
            $bx2 = (int)($slotCenterX + $barW / 2);
            $by2 = (int)($padT + $chartH);
            $by1 = (int)($by2 - $barH);

            if ($barH > 0) {
                imagefilledrectangle($im, $bx1, $by1, $bx2, $by2, $barColor);
                if ($isBase) {
                    // Subtle baseline golden halo around bar
                    imagesetthickness($im, 2 * $scale);
                    imagerectangle($im, $bx1 - 2 * $scale, $by1 - 2 * $scale, $bx2 + 2 * $scale, $by2, $amber);
                }

                // Short value label above bar
                $valLbl = self::formatShortNumber($v);
                $valLblX = (int)($slotCenterX - (strlen($valLbl) * 3 * $scale));
                $valLblY = (int)($by1 - 12 * $scale);
                imagestring($im, 1, max(2, $valLblX), max(0, $valLblY), $valLbl, $textDark);
            } else {
                // Zero baseline notch
                $gray = imagecolorallocate($im, 203, 213, 225);
                imagefilledrectangle($im, $bx1, $by2 - 2 * $scale, $bx2, $by2, $gray);
            }

            // X-axis period label
            $xl = $p['short_label'] ?? $p['label'] ?? '';
            if ($isBase) {
                $xl .= ' [Base]';
            }
            $xlX = (int)($slotCenterX - (strlen($xl) * 3.5 * $scale));
            $xlY = (int)($height - 18 * $scale);
            imagestring($im, 2, max(2, $xlX), max(0, $xlY), $xl, $textDark);

            // Record growth line coordinate if available
            if (isset($p['growth_pct']) && $p['growth_pct'] !== null && is_numeric($p['growth_pct'])) {
                $gp = (float)$p['growth_pct'];
                $gy = $padT + $chartH - (($gp - $growthMin) / $growthRange) * $chartH;
                $gy = max($padT, min($padT + $chartH, $gy));
                $lineCoords[] = [
                    'x'   => (int)$slotCenterX,
                    'y'   => (int)$gy,
                    'gp'  => $gp,
                    'fmt' => $p['growth_fmt'] ?? (($gp > 0 ? '+' : '') . number_format($gp, 1) . '%'),
                    'is_base' => $isBase,
                ];
            }
        }

        // 4. Draw Growth Line + Points Layer
        if (count($lineCoords) > 1) {
            imagesetthickness($im, 3 * $scale);
            for ($i = 0; $i < count($lineCoords) - 1; $i++) {
                imageline($im, $lineCoords[$i]['x'], $lineCoords[$i]['y'], $lineCoords[$i+1]['x'], $lineCoords[$i+1]['y'], $lineColor);
            }
        }

        foreach ($lineCoords as $lc) {
            $lx = $lc['x'];
            $ly = $lc['y'];

            // Distinct circular point
            imagefilledellipse($im, $lx, $ly, 10 * $scale, 10 * $scale, $white);
            imagesetthickness($im, 2 * $scale);
            imageellipse($im, $lx, $ly, 10 * $scale, 10 * $scale, $lineColor);
            imagefilledellipse($im, $lx, $ly, 6 * $scale, 6 * $scale, $lineColor);

            // Growth % label near point
            $gFmt = $lc['fmt'];
            $gX = (int)($lx - (strlen($gFmt) * 3 * $scale));
            $gY = (int)($ly - 14 * $scale);
            imagestring($im, 1, max(2, $gX), max(0, $gY), $gFmt, $lineColor);
        }

        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /**
     * Backwards-compatible alias for operational trend combo chart.
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
        $lineHex = ($strokeHex === '#d97706') ? '#2563eb' : '#f59e0b';
        return self::renderOperationalComboChartPng($points, $unit, $strokeHex, $lineHex, $baselineKey, $title, $w, $h);
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
