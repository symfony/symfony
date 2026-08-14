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
 * @extends \IteratorAggregate<int, string>
 *
 * @experimental
 *
 * @internal
 */
interface LineBufferInterface extends \Countable, \IteratorAggregate
{
    public function getLine(int $index): string;

    /**
     * @return list<string>
     */
    public function slice(int $offset, int $length): array;

    /**
     * @return list<string>
     */
    public function toArray(): array;

    public function getMaxVisibleWidth(): int;
}
