<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Style;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Contributes styles to the cascade of a {@see StyleSheet}.
 *
 * Providers resolve rules a selector map cannot express, like combinators or
 * media queries. Their styles apply after the stylesheet rules and before
 * utility classes and the instance style of the widget.
 *
 * @experimental
 */
interface StyleProviderInterface
{
    /**
     * Called once per frame, before any style is resolved.
     *
     * A provider must invalidate the widgets whose styles changed since the
     * previous frame, so that their render cache is refreshed.
     */
    public function prepare(AbstractWidget $root, MediaContext $media): void;

    /**
     * @return list<Style> The styles that apply to the widget, in cascade order
     */
    public function getStyles(AbstractWidget $widget): array;

    /**
     * @return list<Style> The styles that apply to a sub-element of the widget, in cascade order
     */
    public function getElementStyles(AbstractWidget $widget, string $element): array;
}
