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
use Symfony\Component\TuiHtml\Widget\LoaderWidget;

/**
 * <tui-loader>: a spinner with its text content as the message; it
 * spins until stop() is called, or not at all with the "stopped" attribute.
 *
 * @experimental
 */
final class LoaderElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $loader = new LoaderWidget(trim($builder->getText($element)));
        // Spinning by default, like an indeterminate progress; "stopped" shows the message only
        if (!$element->hasAttribute('stopped')) {
            $loader->start();
        }

        return $loader;
    }
}
