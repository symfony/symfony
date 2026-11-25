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
 * <meter>: a gauge, drawn as a progress bar in percents of its range.
 *
 * @experimental
 */
final class MeterElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $model = \Symfony\Component\HtmlRenderer\Form\NumericControl::meter($element);
        $presentation = \Symfony\Bridge\TuiHtml\Internal\NumericControlPresentation::meter($model);
        $bar = new ProgressBarWidget($presentation['steps'], '[%bar%] %percent:3s%%');
        $bar->setProgress($presentation['progress']);
        $region = $model['region'];
        $bar->setState($region);

        return $bar;
    }
}
