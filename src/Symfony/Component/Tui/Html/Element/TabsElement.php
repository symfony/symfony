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
use Symfony\Component\Tui\Widget\TabPosition;
use Symfony\Component\Tui\Widget\TabsWidget;

/**
 * <tui-tabs>: each child element is a tab, labeled by its title.
 *
 * @experimental
 */
final class TabsElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $position = TabPosition::tryFrom(strtolower($element->getAttribute('position') ?? '')) ?? TabPosition::Top;
        $tabs = new TabsWidget([], $position);
        foreach ($element->children as $child) {
            if (null !== $widget = $builder->buildElement($child)) {
                $tabs->add($widget);
            }
        }

        return $tabs;
    }
}
