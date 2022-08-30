<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Receiver;

use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @author Alexander Melikhov <amelihovv@ya.ru>
 */
interface BlockingReceiverInterface extends ReceiverInterface
{
    /**
     * @param callable(\AMQPEnvelope):?false $callback If callback return false, then processing thread will be
     * returned to PHP script.
     *
     * @throws TransportException If there is an issue communicating with the transport
     */
    public function pull(callable $callback): void;
}
