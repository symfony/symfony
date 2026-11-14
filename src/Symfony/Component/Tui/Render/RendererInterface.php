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
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;

/** @experimental */
interface RendererInterface
{
    public function addStyleSheet(StyleSheet $styleSheet): void;

    public function resolveElement(AbstractWidget $widget, string $element): Style;

    public function invalidateStyles(): void;

    public function createFrameWriter(\Symfony\Component\Tui\Terminal\TerminalInterface $terminal): FrameWriterInterface;

    public function getWidgetRect(AbstractWidget $widget): ?WidgetRect;

    public function renderFrame(ContainerWidget $root, int $columns, int $rows): LineBufferInterface;

    public function renderWidgetLines(AbstractWidget $widget, RenderContext $context): LineBufferInterface;

    public function resolveStyle(AbstractWidget $widget): Style;
}
