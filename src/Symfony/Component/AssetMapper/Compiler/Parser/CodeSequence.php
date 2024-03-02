<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compiler\Parser;

/**
 * Represents a sequence of code (e.g. a string, a comment, a block of code).
 *
 * @author Simon André <smn.andre@gmail.com>
 *
 * @internal
 */
class CodeSequence implements \Stringable
{
    public function __construct(
        private readonly string $type,
        private readonly int $start,
        private readonly int $end,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getEnd(): int
    {
        return $this->end;
    }

    public function __toString(): string
    {
        return sprintf('%s [%d:%d]', $this->type, $this->start, $this->end);
    }
}
