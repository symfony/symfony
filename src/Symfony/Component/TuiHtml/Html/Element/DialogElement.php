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

/**
 * <dialog>: a block container, open by its attribute; HtmlView opens and closes it.
 *
 * A block container still: the content of one HtmlView::setInnerHtml() builds again is replaced in it, the widget
 * kept with what HtmlView made of it, like a modal one.
 *
 * @experimental
 */
final class DialogElement implements ElementFactoryInterface
{
    public function __construct(private readonly ContainerElement $container = new ContainerElement())
    {
    }

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        return $this->container->create($element, $builder)->setState('open', $element->hasAttribute('open'));
    }
}
