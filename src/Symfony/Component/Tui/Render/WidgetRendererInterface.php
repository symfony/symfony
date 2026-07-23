<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Render;

use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Interface for rendering individual widgets and resolving their styles.
 *
 * Used by LayoutEngine and ChromeApplier to call back into the Renderer
 * without creating a circular class dependency.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface WidgetRendererInterface
{
    /**
     * Render a single widget through the full pipeline.
     *
     * @return string[]
     */
    public function renderWidget(AbstractWidget $widget, RenderContext $context): array;

    /**
     * Resolve the style for a widget by merging cascade layers.
     */
    public function resolveStyle(AbstractWidget $widget): Style;

    /**
     * Measure the intrinsic width of a widget: content width + chrome (border/padding).
     *
     * Unlike renderWidget(), this does not pad lines to the allocated width.
     * Used by the layout engine to measure flex: 0 children.
     */
    public function measureIntrinsicWidth(AbstractWidget $widget, int $maxColumns, int $rows): int;
}
