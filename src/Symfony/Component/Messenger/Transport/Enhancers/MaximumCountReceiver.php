<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Enhancers;

use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class MaximumCountReceiver implements ReceiverInterface
{
    private $decoratedReceiver;
    private $maximumNumberOfMessages;

    public function __construct(ReceiverInterface $decoratedReceiver, int $maximumNumberOfMessages)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->maximumNumberOfMessages = $maximumNumberOfMessages;
    }

    public function receive(callable $handler): void
    {
        $receivedMessages = 0;

        $this->decoratedReceiver->receive(function ($message) use ($handler, &$receivedMessages) {
            $handler($message);

            if (++$receivedMessages >= $this->maximumNumberOfMessages) {
                $this->stop();
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }
}
