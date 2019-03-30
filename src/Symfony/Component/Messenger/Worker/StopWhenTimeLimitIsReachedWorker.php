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
 * @author Simon Delicata <simon.delicata@free.fr>
 *
 * @experimental in 4.3
 */
class StopWhenTimeLimitIsReachedWorker implements WorkerInterface
{
    private $decoratedWorker;
    private $timeLimitInSeconds;
    private $logger;

    public function __construct(WorkerInterface $decoratedWorker, int $timeLimitInSeconds, LoggerInterface $logger = null)
    {
        $this->decoratedWorker = $decoratedWorker;
        $this->timeLimitInSeconds = $timeLimitInSeconds;
        $this->logger = $logger;
    }

    public function run(array $options = [], callable $onHandledCallback = null): void
    {
        $startTime = microtime(true);
        $endTime = $startTime + $this->timeLimitInSeconds;

        $this->decoratedWorker->run($options, function (?Envelope $envelope) use ($onHandledCallback, $endTime) {
            if (null !== $onHandledCallback) {
                $onHandledCallback($envelope);
            }

            if ($endTime < microtime(true)) {
                $this->stop();
                if (null !== $this->logger) {
                    $this->logger->info('Worker stopped due to time limit of {timeLimit}s reached', ['timeLimit' => $this->timeLimitInSeconds]);
                }
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedWorker->stop();
    }
}
