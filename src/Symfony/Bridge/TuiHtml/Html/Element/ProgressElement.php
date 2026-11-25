<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Html\Element;

use Symfony\Bridge\TuiHtml\Html\ElementFactoryInterface;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ProgressBarWidget;

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
        $model = \Symfony\Component\HtmlRenderer\Form\NumericControl::progress($element);
        if ($model['indeterminate']) {
            $bar = new ProgressBarWidget(0, '[%bar%]');
            // It moves once mounted: the ticks start when it is attached
            $bar->start();

            return $bar;
        }
        // Progress counts steps: fractions like value="0.4" as percents, a million at most; the value between 0 and max,
        // like HTML
        $presentation = \Symfony\Bridge\TuiHtml\Internal\NumericControlPresentation::progress($model);
        $bar = new ProgressBarWidget($presentation['steps'], '[%bar%] %percent:3s%%');
        $bar->setProgress($presentation['progress']);

        return $bar;
    }
}
