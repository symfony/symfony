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
use Symfony\Component\Tui\Widget\MarqueeWidget;

/**
 * <marquee>: its text moves sideways, like in browsers (see MarqueeWidget).
 *
 * direction (left or right; up and down are left and right), behavior
 * (scroll, slide, alternate), scrollamount in CSS pixels (8 a column, a
 * column at least), scrolldelay in milliseconds (60 at least, unless
 * truespeed) and loop. A marquee holding blocks is a block, still.
 *
 * @experimental
 */
final class MarqueeElement implements ElementFactoryInterface
{
    public function create(\Dom\Element $element, HtmlBuilder $builder): AbstractWidget
    {
        for ($child = $element->firstElementChild; null !== $child; $child = $child->nextElementSibling) {
            if (!$builder->isInline($child) || $builder->getStyleSheet()->mayBeBlock($child)) {
                return (new ContainerElement())->create($element, $builder);
            }
        }

        $direction = strtolower(trim($element->getAttribute('direction') ?? ''));
        $behavior = strtolower(trim($element->getAttribute('behavior') ?? ''));
        $amount = is_numeric($value = $element->getAttribute('scrollamount')) ? (float) $value : 6.0;
        $delay = is_numeric($value = $element->getAttribute('scrolldelay')) ? (float) $value : 85.0;
        if (!$element->hasAttribute('truespeed')) {
            $delay = max(60.0, $delay);
        }
        $loop = is_numeric($value = $element->getAttribute('loop')) ? (int) $value : -1;

        return new MarqueeWidget(
            $builder->getInlineNodes($element),
            \in_array($direction, ['right', 'down'], true) ? 'right' : 'left',
            \in_array($behavior, ['slide', 'alternate'], true) ? $behavior : 'scroll',
            max(1, (int) round($amount / 8)),
            max(1.0, $delay) / 1000,
            0 === $loop ? -1 : $loop,
        );
    }
}
