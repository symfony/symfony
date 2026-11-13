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

/** @experimental */
interface FrameWriterInterface
{
    public function writeFrame(LineBufferInterface $lines): void;

    public function reset(): void;

    public function close(): void;

    public function setScrollOffset(int $offset): void;

    /** @return array{cursor_row: int, line_count: int} */
    public function getState(): array;

    public function followTermuxHeightChange(int $previousRows, int $rows): void;
}
