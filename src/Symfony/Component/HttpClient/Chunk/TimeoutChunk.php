<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Chunk;

use Symfony\Component\HttpClient\Exception\TransportException;

/**
 * @internal
 */
class TimeoutChunk extends ErrorChunk
{
    /**
     * {@inheritdoc}
     */
    public function __construct(int $offset, string $url, float $seconds)
    {
        parent::__construct($offset, TransportException::readTimeoutReached($url, $seconds));
    }

    /**
     * {@inheritdoc}
     */
    public function isTimeout(): bool
    {
        $this->didThrow = true;

        return true;
    }
}
