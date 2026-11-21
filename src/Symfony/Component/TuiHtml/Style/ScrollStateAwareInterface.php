<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * A layout-aware style provider whose styles depend on how widgets are scrolled, like CSS scroll-state() queries.
 *
 * @experimental
 */
interface ScrollStateAwareInterface extends LayoutAwareStyleProviderInterface
{
    /**
     * Gives the scroll state of the widgets in the frame just rendered, read in afterLayout().
     *
     * @param \Closure(AbstractWidget): array{stuck: list<'top'|'bottom'>, snapped: list<'x'|'y'>, scrollable: list<'top'|'bottom'|'left'|'right'>, scrolled: list<'top'|'bottom'|'left'|'right'>, targetCurrent: bool, offset: array{array{}|array{int, int}, array{}|array{int, int}}} $scrollStateOf The sides a sticky widget is stuck to, the axes a widget is snapped on in its scroll container, the sides a scroll container can be scrolled to, the side it was last scrolled to, whether a child is the one the current ::scroll-marker of its scroll container stands for, and where a widget starts in the content of its scroll container and how long it is, down then sideways
     */
    public function setScrollStateResolver(\Closure $scrollStateOf): void;
}
