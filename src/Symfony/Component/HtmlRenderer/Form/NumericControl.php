<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Form;

/** @internal */
final class NumericControl
{
    public static function progress(\Dom\Element $element): array
    {
        $max = self::number($element, 'max', 1);
        $max = $max > 0 ? $max : 1.0;
        $value = max(0.0, min($max, self::number($element, 'value', 0)));

        return ['min' => 0.0, 'max' => $max, 'value' => $value, 'fraction' => $value / $max, 'indeterminate' => !is_numeric($element->getAttribute('value'))];
    }

    public static function meter(\Dom\Element $element): array
    {
        $min = self::number($element, 'min', 0);
        $max = max($min, self::number($element, 'max', 1));
        $value = max($min, min($max, self::number($element, 'value', $min)));
        $low = max($min, min($max, self::number($element, 'low', $min)));
        $high = max($low, min($max, self::number($element, 'high', $max)));
        $optimum = max($min, min($max, self::number($element, 'optimum', $min / 2 + $max / 2)));
        $region = match (true) {
            $optimum < $low => $value < $low ? 'optimum' : ($value <= $high ? 'suboptimum' : 'even-less-good'),
            $optimum > $high => $value > $high ? 'optimum' : ($value >= $low ? 'suboptimum' : 'even-less-good'),
            default => $value >= $low && $value <= $high ? 'optimum' : 'suboptimum',
        };

        return ['min' => $min, 'max' => $max, 'value' => $value, 'fraction' => $max > $min ? ($value / 2 - $min / 2) / ($max / 2 - $min / 2) : 0.0, 'region' => $region];
    }

    public static function range(\Dom\Element $element, ?string $currentValue = null): array
    {
        $min = self::number($element, 'min', 0);
        $max = max($min, self::number($element, 'max', 100));
        $step = self::number($element, 'step', 1);
        $step = $step > 0 ? $step : 1.0;
        $value = null !== $currentValue && is_numeric($currentValue) && is_finite((float) $currentValue) ? (float) $currentValue : self::number($element, 'value', $min / 2 + $max / 2);
        $value = NumberStepper::constrain($value, $min, $max, $step);

        return ['min' => $min, 'max' => $max, 'value' => $value, 'step' => $step, 'fraction' => $max > $min ? ($value / 2 - $min / 2) / ($max / 2 - $min / 2) : 0.0];
    }

    private static function number(\Dom\Element $element, string $attribute, float $default): float
    {
        $value = $element->getAttribute($attribute);

        return is_numeric($value) && is_finite((float) $value) ? (float) $value : $default;
    }
}
