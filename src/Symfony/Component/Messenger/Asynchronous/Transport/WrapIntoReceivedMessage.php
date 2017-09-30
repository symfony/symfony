<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Asynchronous\Transport;

use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class WrapIntoReceivedMessage implements ReceiverInterface
{
    private $decoratedReceiver;

    public function __construct(ReceiverInterface $decoratedConsumer)
    {
        $this->decoratedReceiver = $decoratedConsumer;
    }

    public function receive(): iterable
    {
        $iterator = $this->decoratedReceiver->receive();

        foreach ($iterator as $message) {
            try {
                yield new ReceivedMessage($message);
            } catch (\Throwable $e) {
                if (!$iterator instanceof \Generator) {
                    throw $e;
                }

                $iterator->throw($e);
            }
        }
    }
}
