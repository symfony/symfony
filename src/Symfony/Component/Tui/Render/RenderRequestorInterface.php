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

/**
 * Capability interface for managing the render lifecycle.
 *
 * Used by internal collaborators (FocusManager, MouseCoordinator)
 * that need to trigger or flush a render pass without depending
 * on the full Tui API.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface RenderRequestorInterface
{
    /**
     * Request a render on the next tick.
     *
     * @param bool $force If true, clear all cached state and do a full re-render
     */
    public function requestRender(bool $force = false): void;

    /**
     * Flush any pending render immediately.
     *
     * Unlike requestRender() which defers to the next tick, this
     * synchronously renders the current frame. Used when up-to-date
     * widget positions are needed before further processing (e.g.
     * mouse hit-testing after a screen transition).
     */
    public function processRender(): void;
}
