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

    public function receive(): iterable
    {
        $iterator = $this->decoratedReceiver->receive();
        $receivedMessages = 0;

        foreach ($iterator as $message) {
            try {
                yield $message;
            } catch (\Throwable $e) {
                if (!$iterator instanceof \Generator) {
                    throw $e;
                }

                $iterator->throw($e);
            }

            if (++$receivedMessages >= $this->maximumNumberOfMessages) {
                break;
            }
        }
    }
}
