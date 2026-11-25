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
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Block containers: <div>, <section>, <main>, <ul>...
 *
 * @experimental
 */
final class ContainerElement implements ElementFactoryInterface
{
    /** Tui extensions mapped to the default layout classes */
    private const CLASSES = ['tui-columns' => 'columns', 'tui-column' => 'column'];

    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        $container = new ContainerWidget();
        if (null !== $class = self::CLASSES[strtolower($element->localName)] ?? null) {
            $container->addStyleClass($class);
        }
        $builder->buildChildren($element, $container);

        return $container;
    }
}
