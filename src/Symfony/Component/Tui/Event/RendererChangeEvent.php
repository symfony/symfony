<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Event;

use Symfony\Component\Tui\Render\RendererInterface;
use Symfony\Component\Tui\Widget\AbstractWidget;

/**
 * Event dispatched when Tui::setRenderer() selects another renderer, before the next frame.
 *
 * What was given to the previous renderer, like the styles of a view, can be moved to the new one.
 *
 * @experimental
 */
class RendererChangeEvent extends AbstractEvent
{
    public function __construct(
        AbstractWidget $target,
        private readonly RendererInterface $previousRenderer,
        private readonly RendererInterface $renderer,
    ) {
        parent::__construct($target);
    }

    public function getPreviousRenderer(): RendererInterface
    {
        return $this->previousRenderer;
    }

    public function getRenderer(): RendererInterface
    {
        return $this->renderer;
    }
}
