<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Amp\Parallel\Worker\ContextWorkerPool;
use Symfony\Component\Messenger\Stamp\FutureStamp;

use function Amp\async;
use function Amp\Parallel\Worker\workerPool;

/**
 * Using this bus will enable concurrent message processing without the need for multiple workers
 * using multiple processes or threads
 * It requires a ZTS build of PHP 8.2+ and ext-parallel to create threads; otherwise, it will use processes.
 */
final class ParallelMessageBus implements MessageBusInterface
{
    public static ?ContextWorkerPool $worker = null;

    public function __construct(private array $something, private readonly string $env, private readonly string $debug, private readonly string $projectdir)
    {
        if (!class_exists(ContextWorkerPool::class)) {
            throw new \LogicException(sprintf('Package "amp/parallel" is required to use the "%s". Try running "composer require amphp/parallel".', self::class));
        }
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $worker = (self::$worker ??= workerPool());

        $envelope = Envelope::wrap($message, $stamps);
        $task = new DispatchTask($envelope, $stamps, $this->env, $this->debug, $this->projectdir);

        $future = async(function () use ($worker, $task) {
            return $worker->submit($task);
        });

        return $envelope->with(new FutureStamp($future));
    }
}
