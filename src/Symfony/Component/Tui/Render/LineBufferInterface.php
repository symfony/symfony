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
 * The lines a widget or a frame renders to, one string of ANSI text per row.
 *
 * A renderer may give its own implementation, like one that builds its
 * lines only when they are read.
 *
 * @extends \IteratorAggregate<int, string>
 *
 * @experimental
 */
interface LineBufferInterface extends \Countable, \IteratorAggregate
{
    /**
     * Returns a line, from 0.
     *
     * @throws \Symfony\Component\Tui\Exception\OutOfBoundsException when there is no such line
     */
    public function getLine(int $index): string;

    /**
     * @return list<string>
     */
    public function slice(int $offset, int $length): array;

    /**
     * @return list<string>
     */
    public function toArray(): array;

    /**
     * Returns the width, in terminal columns, of the widest line.
     */
    public function getMaxVisibleWidth(): int;
}
