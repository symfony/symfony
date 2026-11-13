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
 * A layout-aware style provider whose styles depend on the size of the content of widgets, like CSS
 * interpolate-size: allow-keywords and calc-size().
 *
 * @experimental
 */
interface IntrinsicSizeAwareInterface extends LayoutAwareStyleProviderInterface
{
    /**
     * Gives the height of widgets as their content makes it, their padding and border included, in the frame just
     * rendered.
     *
     * @param \Closure(AbstractWidget): ?int $rowsOf The rows, null for a widget not drawn
     */
    public function setIntrinsicSizeResolver(\Closure $rowsOf): void;
}
