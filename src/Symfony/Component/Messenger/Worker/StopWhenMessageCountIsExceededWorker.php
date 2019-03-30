<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Worker;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\WorkerInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.3
 */
class StopWhenMessageCountIsExceededWorker implements WorkerInterface
{
    private $decoratedWorker;
    private $maximumNumberOfMessages;
    private $logger;

    public function __construct(WorkerInterface $decoratedWorker, int $maximumNumberOfMessages, LoggerInterface $logger = null)
    {
        $this->decoratedWorker = $decoratedWorker;
        $this->maximumNumberOfMessages = $maximumNumberOfMessages;
        $this->logger = $logger;
    }

    public function run(array $options = [], callable $onHandledCallback = null): void
    {
        $receivedMessages = 0;

        $this->decoratedWorker->run($options, function (?Envelope $envelope) use ($onHandledCallback, &$receivedMessages) {
            if (null !== $onHandledCallback) {
                $onHandledCallback($envelope);
            }

            if (null !== $envelope && ++$receivedMessages >= $this->maximumNumberOfMessages) {
                $this->stop();
                if (null !== $this->logger) {
                    $this->logger->info('Worker stopped due to maximum count of {count} exceeded', ['count' => $this->maximumNumberOfMessages]);
                }
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedWorker->stop();
    }
}
