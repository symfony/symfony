<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Html\Element;

use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Widget\ProgressBarWidget;

/**
 * <progress>: determinate with a value, its bar and percentage; indeterminate without, its bar moving (still with
 * prefers-reduced-motion); no step count, like a browser.
 *
 * @experimental
 */
final class ProgressElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $value = $element->getAttribute('value');
        // Like HTML: a max that is not above 0 is 1
        $max = is_numeric($max = $element->getAttribute('max')) && is_finite((float) $max) && (float) $max > 0 ? (float) $max : 1.0;
        if (!is_numeric($value)) {
            $bar = new ProgressBarWidget(0, '[%bar%]');
            // It moves once mounted: the ticks start when it is attached
            $bar->start();

            return $bar;
        }
        // Progress counts steps: fractions like value="0.4" as percents, a million at most; the value between 0 and max,
        // like HTML
        $steps = $max <= 1 ? 100 : (int) min(round($max), 1000000);
        $value = is_finite((float) $value) ? max(0.0, min($max, (float) $value)) : 0.0;
        $bar = new ProgressBarWidget($steps, '[%bar%] %percent:3s%%');
        $bar->setProgress((int) round($value / $max * $steps));

        return $bar;
    }
}
