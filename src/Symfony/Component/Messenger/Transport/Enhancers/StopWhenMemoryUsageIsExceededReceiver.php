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
class StopWhenMemoryUsageIsExceededReceiver implements ReceiverInterface
{
    private $decoratedReceiver;
    private $memoryLimit;
    private $logger;
    private $memoryResolver;

    public function __construct(ReceiverInterface $decoratedReceiver, int $memoryLimit, LoggerInterface $logger = null, callable $memoryResolver = null)
    {
        $this->decoratedReceiver = $decoratedReceiver;
        $this->memoryLimit = $memoryLimit;
        $this->logger = $logger;
        $this->memoryResolver = $memoryResolver ?: function () {
            return \memory_get_usage();
        };
    }

    public function receive(callable $handler): void
    {
        $this->decoratedReceiver->receive(function (?Envelope $envelope) use ($handler) {
            $handler($envelope);

            $memoryResolver = $this->memoryResolver;
            if ($memoryResolver() > $this->memoryLimit) {
                $this->stop();
                if (null !== $this->logger) {
                    $this->logger->info('Receiver stopped due to memory limit of {limit} exceeded', array('limit' => $this->memoryLimit));
                }
            }
        });
    }

    public function stop(): void
    {
        $this->decoratedReceiver->stop();
    }
}
