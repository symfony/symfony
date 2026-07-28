<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Execution;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Execution\Message\DeferredEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\DispatchEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\FlushBatchHandlersMessage;
use Symfony\Component\Messenger\Execution\Message\HandledEnvelopeMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Runtime\RuntimeInterface;
use Symfony\Component\Runtime\SymfonyRuntime;
use Symfony\Contracts\Service\ContainerProviderInterface;

/**
 * @internal
 *
 * @implements Task<null, DispatchEnvelopeMessage|FlushBatchHandlersMessage, DeferredEnvelopeMessage|HandledEnvelopeMessage>
 */
final class DispatchTask implements Task
{
    public function __construct(
        private readonly string $console,
        private readonly int $resetInterval = 1,
        private readonly ?int $memoryLimit = null,
    ) {
    }

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        ini_set('zend.exception_ignore_args', '1');

        [$bus, $container] = $this->bootstrap();
        $count = 0;
        $unacks = new DeferredBatchMessageQueue();

        while ($message = $channel->receive($cancellation)) {
            if ($message instanceof FlushBatchHandlersMessage) {
                $this->flush($bus, $channel, $message->force, $unacks);

                continue;
            }

            // the channel carries whatever the parent serialised, so this stays a runtime check
            // @phpstan-ignore instanceof.alwaysTrue
            if (!$message instanceof DispatchEnvelopeMessage) {
                continue;
            }

            $requestId = $message->requestId;
            $acked = false;
            $ack = static function (Envelope $handledEnvelope, ?\Throwable $handledError = null) use (&$acked, $requestId, $channel): void {
                $acked = true;

                if (null !== $handledError) {
                    $handledEnvelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($handledEnvelope);
                    $handledError = ParallelExecutionFailureSanitizer::sanitizeError($handledError, $handledEnvelope);
                }

                self::sendHandled($channel, $requestId, $handledEnvelope->withoutAll(AckStamp::class), $handledError);
            };

            try {
                $envelope = $bus->dispatch($message->envelope->with(new AckStamp($ack)));
                $envelope = $envelope->withoutAll(AckStamp::class);
            } catch (\Throwable $e) {
                $envelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($message->envelope);
                self::sendHandled($channel, $requestId, $envelope, ParallelExecutionFailureSanitizer::sanitizeError($e, $envelope));

                unset($ack, $acked);

                continue;
            }

            $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

            if (!$acked && !$noAutoAckStamp) {
                self::sendHandled($channel, $requestId, $envelope, null);
            } elseif ($noAutoAckStamp) {
                $channel->send(new DeferredEnvelopeMessage($requestId));
                $unacks->add($noAutoAckStamp->getHandlerDescriptor()->getBatchHandler(), $requestId, $envelope, $acked, microtime(true));
            }

            // break the shared reference set so that each message tracks its own ack flag
            unset($ack, $acked);

            if ($this->resetInterval > 0 && 0 === ++$count % $this->resetInterval && $container->has('services_resetter')) {
                $container->get('services_resetter')->reset();
            }

            if (null !== $this->memoryLimit && memory_get_usage(true) >= $this->memoryLimit) {
                $this->flush($bus, $channel, true, $unacks);
                break;
            }
        }

        return null;
    }

    private function flush(MessageBusInterface $bus, Channel $channel, bool|float $force, DeferredBatchMessageQueue $unacks): void
    {
        if (!$unacks->hasPending()) {
            return;
        }

        $toFlush = $unacks->popFlushable($force, microtime(true));

        foreach ($toFlush as $handler) {
            $deferredMessage = $toFlush[$handler];
            $requestId = $deferredMessage->context;
            $envelope = $deferredMessage->envelope;

            try {
                $envelope = $bus->dispatch($envelope->with(new FlushBatchHandlersStamp(false !== $force)))->withoutAll(AckStamp::class);
            } catch (\Throwable $e) {
                $envelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($envelope);
                self::sendHandled($channel, $requestId, $envelope, ParallelExecutionFailureSanitizer::sanitizeError($e, $envelope));

                continue;
            }

            $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

            if (!$deferredMessage->acked && !$noAutoAckStamp) {
                self::sendHandled($channel, $requestId, $envelope, null);
            } elseif ($noAutoAckStamp) {
                $unacks->add($noAutoAckStamp->getHandlerDescriptor()->getBatchHandler(), $requestId, $envelope, $deferredMessage->acked, microtime(true));
            }
        }
    }

    private static function sendHandled(Channel $channel, int $requestId, Envelope $envelope, ?\Throwable $error): void
    {
        try {
            $channel->send(new HandledEnvelopeMessage($requestId, $envelope, $error));
        } catch (\Throwable) {
            // handler results may not be serializable; retry without them but keep the
            // handler names so that retries don't run already-successful handlers again
            $handledStamps = $envelope->all(HandledStamp::class);
            $envelope = $envelope->withoutAll(HandledStamp::class);

            foreach ($handledStamps as $stamp) {
                $envelope = $envelope->with(new HandledStamp(null, $stamp->getHandlerName()));
            }

            $channel->send(new HandledEnvelopeMessage($requestId, $envelope, $error));
        }
    }

    private function bootstrap(): array
    {
        if (!is_file($console = $this->console)) {
            throw new \LogicException(\sprintf('Unable to bootstrap the Messenger worker: the entry point "%s" was not found.', $console));
        }

        ob_start(static function ($chunk, $flag) {
            if (($flag & \PHP_OUTPUT_HANDLER_START) && str_starts_with($chunk, '#') && false !== $i = strpos($chunk, "\n")) {
                $chunk = substr($chunk, 1 + $i);
            }

            return $chunk;
        }, 1);
        try {
            // the argument is read with func_get_arg() so that the required file does not
            // inherit a variable from this scope
            // @phpstan-ignore arguments.count
            $app = (static fn () => require func_get_arg(0))->bindTo(null, null)($console);
        } finally {
            ob_end_flush();
        }

        if ($app instanceof \Closure) {
            $app = $this->resolveRuntimeApplication($app);
        }

        if (!$app instanceof ContainerProviderInterface) {
            throw new \LogicException(\sprintf('The "%s" entry point must return an instance of "%s", got "%s".', $console, ContainerProviderInterface::class, get_debug_type($app)));
        }

        $container = $app->getContainer();

        return [$container->get('messenger.routable_message_bus'), $container];
    }

    private function resolveRuntimeApplication(\Closure $app): object
    {
        if (!interface_exists(RuntimeInterface::class)) {
            throw new \LogicException('Package "symfony/runtime" is required to bootstrap the Messenger worker from "bin/console". Try running "composer require symfony/runtime".');
        }

        $runtimeClass = $_SERVER['APP_RUNTIME'] ?? $_ENV['APP_RUNTIME'] ?? SymfonyRuntime::class;

        if (!class_exists($runtimeClass)) {
            throw new \LogicException(\sprintf('Runtime class "%s" not found while bootstrapping the Messenger worker.', $runtimeClass));
        }

        $options = $_SERVER['APP_RUNTIME_OPTIONS'] ?? $_ENV['APP_RUNTIME_OPTIONS'] ?? [];

        if (\is_string($options)) {
            $options = json_decode($options, true, 512, \JSON_THROW_ON_ERROR);
        }

        $runtime = new $runtimeClass(array_replace($options, [
            'project_dir' => \dirname($this->console, 2),
            'error_handler' => false,
        ]));

        [$app, $args] = $runtime->getResolver($app)->resolve();
        $app = $app(...$args);

        if (!\is_object($app)) {
            throw new \LogicException(\sprintf('Invalid return value while bootstrapping the Messenger worker: object expected, "%s" returned.', get_debug_type($app)));
        }

        return $app;
    }
}
