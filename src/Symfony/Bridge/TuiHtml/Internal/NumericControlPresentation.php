<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Internal;

use Symfony\Component\HtmlRenderer\Form\NumberStepper;

/** @internal */
final class NumericControlPresentation
{
    public static function progress(array $model): array
    {
        if ($model['indeterminate']) {
            return ['steps' => 0, 'progress' => 0, 'fraction' => 0.0, 'label' => ''];
        }
        $steps = $model['max'] <= 1 ? 100 : (int) min(round($model['max']), 1000000);
        $progress = (int) round($model['fraction'] * $steps);

        return ['steps' => $steps, 'progress' => $progress, 'fraction' => $steps > 0 ? $progress / $steps : 0.0, 'label' => self::percentage($progress, $steps)];
    }

    public static function meter(array $model): array
    {
        $progress = (int) round($model['fraction'] * 100);

        return ['steps' => 100, 'progress' => $progress, 'fraction' => $progress / 100, 'label' => self::percentage($progress, 100)];
    }

    public static function range(array $model): array
    {
        $value = NumberStepper::format($model['value'], $model['step']);
        $columns = max(1 + \strlen($value), self::rangeWidth($model['min'], $model['max'], $model['step']));

        return ['label' => str_pad(' '.$value, $columns, ' ', \STR_PAD_LEFT), 'columns' => $columns];
    }

    public static function rangeWidth(float $min, float $max, float $step): int
    {
        $decimals = NumberStepper::decimals($step);
        $fraction = $decimals > 0 ? 1 + $decimals : 0;
        $digits = static fn (float $bound): int => \strlen((string) (int) floor(abs($bound)));

        return max(1 + \strlen(NumberStepper::format($min, $step)), 1 + \strlen(NumberStepper::format(NumberStepper::constrain($max, $min, $max, $step), $step)),
            1 + max(($min < 0 ? 1 : 0) + $digits($min), ($max < 0 ? 1 : 0) + $digits($max)) + $fraction);
    }

    private static function percentage(int $progress, int $steps): string
    {
        $percent = $steps > 0 ? (int) floor($progress / $steps * 100) : 0;

        return \sprintf(' %3d%%', $percent);
    }
}
