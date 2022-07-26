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

final class Result
{
    public function __construct(private \SplObjectStorage $ackMap)
    {
    }

    public function ok(object $message, mixed $result = null): void
    {
        $this->ackMap[$message]->ack($result);
    }

    public function error(object $message, \Throwable $e): void
    {
        $this->ackMap[$message]->nack($e);
    }
}
