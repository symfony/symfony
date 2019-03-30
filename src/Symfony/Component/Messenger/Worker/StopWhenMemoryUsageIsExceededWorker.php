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
class StopWhenMemoryUsageIsExceededWorker implements WorkerInterface
{
    private $decoratedWorker;
    private $memoryLimit;
    private $logger;
    private $memoryResolver;

    public function __construct(WorkerInterface $decoratedWorker, int $memoryLimit, LoggerInterface $logger = null, callable $memoryResolver = null)
    {
        $this->decoratedWorker = $decoratedWorker;
        $this->memoryLimit = $memoryLimit;
        $this->logger = $logger;
        $this->memoryResolver = $memoryResolver ?: function () {
            return \memory_get_usage();
        };
    }

    public function run(array $options = [], callable $onHandledCallback = null): void
    {
        $this->decoratedWorker->run($options, function (?Envelope $envelope) use ($onHandledCallback) {
            if (null !== $onHandledCallback) {
                $onHandledCallback($envelope);
            }

            $memoryResolver = $this->memoryResolver;
            if ($memoryResolver() > $this->memoryLimit) {
                $this->stop();
                if (null !== $this->logger) {
                    $this->logger->info('Worker stopped due to memory limit of {limit} exceeded', ['limit' => $this->memoryLimit]);
                }
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedWorker->stop();
    }
}
