<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

class CountBatchStrategy implements BatchStrategyInterface
{
    private int $bufferSize = 0;

    public function __construct(private readonly int $flushSize)
    {
    }

    public function shouldHandle(object $lastMessage): bool
    {
        return ++$this->bufferSize >= $this->flushSize;
    }

    public function beforeHandle(): void
    {
        $this->bufferSize = 0;
    }

    public function afterHandle(): void
    {
        // no operation
    }
}
