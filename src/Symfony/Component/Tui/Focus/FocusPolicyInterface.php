<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Focus;

use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Decides which widgets take the focus and in what order, for a renderer
 * that knows more about them than the widget tree (see RendererInterface).
 *
 * Tui asks it again when it selects the renderer and after each frame: a
 * focused widget it no longer allows loses the focus, see
 * FocusManager::revalidateFocus().
 *
 * @experimental
 */
interface FocusPolicyInterface
{
    /**
     * Whether a widget can take the focus, like one that is drawn.
     *
     * Widgets with a negative tab index or the disabled state (see
     * AbstractWidget::STATE_DISABLED) are skipped before.
     */
    public function allowsFocus(AbstractWidget $widget): bool;

    /**
     * Puts widgets in the order the focus goes through them.
     *
     * Tui gives the widgets of the cycle without a positive tab index, in the
     * order they were added; those with a positive tab index come first
     * whatever is returned. A renderer that draws widgets in another order
     * than the tree, like a grid filled column by column, returns them in the
     * order they are drawn; the others return them as given.
     *
     * Called on each focus move: keep it cheap when nothing is reordered.
     *
     * @template T of AbstractWidget
     *
     * @param list<T> $widgets
     *
     * @return list<T> The same widgets
     */
    public function orderFocusCycle(array $widgets): array;
}
