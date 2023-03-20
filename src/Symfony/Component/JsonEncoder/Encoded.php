<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder;

/**
 * Represents an encoding result.
 * Can be iterated or casted to string.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 *
 * @implements \IteratorAggregate<string>
 */
final readonly class Encoded implements \IteratorAggregate, \Stringable
{
    /**
     * @param \Traversable<string> $chunks
     */
    public function __construct(
        private \Traversable $chunks,
    ) {
    }

    public function getIterator(): \Traversable
    {
        return $this->chunks;
    }

    public function __toString(): string
    {
        $encoded = '';
        foreach ($this->chunks as $chunk) {
            $encoded .= $chunk;
        }

        return $encoded;
    }
}
