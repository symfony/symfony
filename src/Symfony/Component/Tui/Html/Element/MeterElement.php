<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Html\Element;

use Symfony\Component\Tui\Html\ElementFactoryInterface;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ProgressBarWidget;

/**
 * <meter>: a gauge, drawn as a progress bar in percents of its range.
 *
 * @experimental
 */
final class MeterElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $min = is_numeric($min = $element->getAttribute('min')) ? (float) $min : 0.0;
        $max = is_numeric($max = $element->getAttribute('max')) ? (float) $max : 1.0;
        $value = is_numeric($value = $element->getAttribute('value')) ? (float) $value : $min;

        // Its bar and its percentage, which says it without colors, like <progress>: no counts of steps, which a meter
        // has none of
        $bar = new ProgressBarWidget(100, format: '[%bar%] %percent:3s%%');
        $range = $max - $min;
        $value = max($min, min($max, $value));
        $bar->setProgress($range > 0 ? (int) round(($value - $min) / $range * 100) : 0);

        // The region of the value, like HTML: :optimum, :suboptimum or :even-less-good, which the default stylesheet colors
        $low = is_numeric($low = $element->getAttribute('low')) ? max($min, min($max, (float) $low)) : $min;
        $high = is_numeric($high = $element->getAttribute('high')) ? max($low, min($max, (float) $high)) : $max;
        $optimum = is_numeric($optimum = $element->getAttribute('optimum')) ? max($min, min($max, (float) $optimum)) : ($min + $max) / 2;
        $region = match (true) {
            $optimum < $low => $value < $low ? 'optimum' : ($value <= $high ? 'suboptimum' : 'even-less-good'),
            $optimum > $high => $value > $high ? 'optimum' : ($value >= $low ? 'suboptimum' : 'even-less-good'),
            default => $value >= $low && $value <= $high ? 'optimum' : 'suboptimum',
        };
        $bar->setState($region);

        return $bar;
    }
}
