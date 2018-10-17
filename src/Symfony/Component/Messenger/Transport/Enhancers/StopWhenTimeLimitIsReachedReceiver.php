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

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

/**
 * @author Simon Delicata <simon.delicata@free.fr>
 */
class StopWhenTimeLimitIsReachedReceiver implements ReceiverInterface
{
    private $decoratedReceiver;
    private $timeLimitInSeconds;
    private $logger;

    public function __construct(ReceiverInterface $decoratedReceiver, int $timeLimitInSeconds, LoggerInterface $logger = null)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->timeLimitInSeconds = $timeLimitInSeconds;
        $this->logger = $logger;
    }

    public function receive(callable $handler): void
    {
        $startTime = microtime(true);
        $endTime = $startTime + $this->timeLimitInSeconds;

        $this->decoratedReceiver->receive(function (?Envelope $envelope) use ($handler, $endTime) {
            $handler($envelope);

            if ($endTime < microtime(true)) {
                $this->stop();
                if (null !== $this->logger) {
                    $this->logger->info('Receiver stopped due to time limit of {timeLimit}s reached', array('timeLimit' => $this->timeLimitInSeconds));
                }
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }
}
