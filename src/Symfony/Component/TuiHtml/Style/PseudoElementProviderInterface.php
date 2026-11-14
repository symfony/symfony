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
 * A style provider whose ::before and ::after can be placed out of the flow, like a tooltip in
 * .tip:hover::after { position: absolute }: the renderer draws them over the frame, like absolute widgets.
 *
 * @experimental
 */
interface PseudoElementProviderInterface extends StyleProviderInterface
{
    /**
     * The absolute and fixed pseudo-elements of a widget, as computed last: their text and their style, by name.
     *
     * @return array<'before'|'after', array{string, Style}>
     */
    public function getPositionedPseudoElements(AbstractWidget $widget): array;
}
