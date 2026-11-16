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
 * A renderer that decorates another one, which code looking for a given
 * renderer finds through it.
 *
 * Code that needs a renderer of its own, like the views of a rendering
 * backend, follows getDecoratedRenderer() from Tui::getRenderer() until it
 * finds it.
 *
 * @experimental
 */
interface RendererDecoratorInterface extends RendererInterface
{
    /**
     * Returns the renderer this one decorates.
     */
    public function getDecoratedRenderer(): RendererInterface;
}
