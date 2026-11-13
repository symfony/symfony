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

use Symfony\Component\Tui\Render\WidgetRect;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * A style provider whose styles depend on the layout, like CSS container queries.
 *
 * After a frame is laid out, the renderer gives it the size of the widgets;
 * when its styles change because of them, the frame is rendered once more.
 *
 * @experimental
 */
interface LayoutAwareStyleProviderInterface extends StyleProviderInterface
{
    /**
     * @param \Closure(AbstractWidget): ?WidgetRect $rectOf The rect of a widget in the frame just rendered
     *
     * @return bool Whether styles changed, and the frame must be rendered again
     */
    public function afterLayout(AbstractWidget $root, \Closure $rectOf): bool;
}
