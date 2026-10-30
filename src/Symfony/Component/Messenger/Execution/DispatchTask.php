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
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Execution\Message\DeferredEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\DispatchEnvelopeMessage;
use Symfony\Component\Messenger\Execution\Message\FlushBatchHandlersMessage;
use Symfony\Component\Messenger\Execution\Message\HandledEnvelopeMessage;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\AckStamp;
use Symfony\Component\Messenger\Stamp\FlushBatchHandlersStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\NoAutoAckStamp;
use Symfony\Component\Messenger\Stamp\TrustStamp;
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
        $heldAcks = null;

        while ($message = $channel->receive($cancellation)) {
            if ($message instanceof FlushBatchHandlersMessage) {
                $this->flush($bus, $channel, $message->force, $unacks, $heldAcks);

                continue;
            }

            // the channel carries whatever the parent serialised, so this stays a runtime check
            // @phpstan-ignore instanceof.alwaysTrue
            if (!$message instanceof DispatchEnvelopeMessage) {
                continue;
            }

            $requestId = $message->requestId;
            $acked = false;
            $acks = [];
            $dispatching = true;
            $batch = null;
            $flushes = [];
            $combine = static function (array $acks) use (&$batch, &$acked): ?array {
                if ($ack = DeferredBatchMessageQueue::combineAcks($batch, $acks)) {
                    $acked = true;
                }

                return $ack;
            };
            $ack = static function (Envelope $handledEnvelope, ?\Throwable $handledError = null) use (&$acks, &$dispatching, &$heldAcks, $requestId, $channel, $combine): void {
                if (null !== $handledError) {
                    $handledEnvelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($handledEnvelope);
                    $handledError = ParallelExecutionFailureSanitizer::sanitizeError($handledError, $handledEnvelope);
                }

                if ($dispatching) {
                    $acks[] = [$handledEnvelope->withoutAll(AckStamp::class), $handledError];

                    return;
                }

                if (null !== $heldAcks) {
                    $heldAcks[] = [$requestId, $handledEnvelope->withoutAll(AckStamp::class), $handledError, $combine];

                    return;
                }

                self::sendBatchAck($channel, $combine([[$requestId, $handledEnvelope->withoutAll(AckStamp::class), $handledError]]));
            };
            $deferFlush = static function (HandlerDescriptor $handlerDescriptor) use (&$flushes): void {
                $flushes[] = new NoAutoAckStamp($handlerDescriptor);
            };

            $envelope = $message->envelope;

            // a TrustStamp counts only in the process that created it
            if ($message->trusted) {
                $envelope = $envelope->withoutAll(TrustStamp::class)->with(TrustStamp::trusted());
            }

            $heldAcks = [];

            try {
                $envelope = $bus->dispatch($envelope->with(new AckStamp($ack, $deferFlush)));
            } catch (\Throwable $e) {
                self::releaseHeldAcks($channel, $heldAcks, true);

                if ($e instanceof HandlerFailedException && $e->getEnvelope()->last(NoAutoAckStamp::class)) {
                    $acks = [];
                    $e = self::flushBatchHandlers($bus, $channel, $heldAcks, $e, $acks);
                }

                // when the bus throws, its exception wins over the acks sent during the dispatch
                $dispatching = false;
                $envelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($message->envelope);
                self::sendHandled($channel, $requestId, $envelope, ParallelExecutionFailureSanitizer::sanitizeError($e, $envelope));

                unset($ack, $acked, $acks, $dispatching, $batch, $combine, $flushes);

                continue;
            }

            self::releaseHeldAcks($channel, $heldAcks, false);
            $dispatching = false;
            $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

            if ($acks || $noAutoAckStamp) {
                $batch = [$envelope->withoutAll(AckStamp::class), []];
                self::sendBatchAck($channel, $combine(array_map(static fn (array $ack) => [$requestId, ...$ack], $acks)));
            } else {
                self::sendHandled($channel, $requestId, $envelope->withoutAll(AckStamp::class), null);
            }

            if ($flushes) {
                $heldAcks = [];
                try {
                    $bus->dispatch($envelope->withoutAll(AckStamp::class)->withoutAll(NoAutoAckStamp::class)->with(new FlushBatchHandlersStamp(true), ...$flushes));
                    self::releaseHeldAcks($channel, $heldAcks, false);
                } catch (\Throwable) {
                    // the messages that the batch didn't settle wait for the next flush of the worker
                    self::releaseHeldAcks($channel, $heldAcks, true);
                }
            }

            if ($noAutoAckStamp && !$acked) {
                $channel->send(new DeferredEnvelopeMessage($requestId));

                foreach ($envelope->all(NoAutoAckStamp::class) as $stamp) {
                    $unacks->add($stamp->getHandlerDescriptor()->getBatchHandler(), $requestId, $envelope, $acked, microtime(true));
                }
            }

            // break the shared reference set so that each message tracks its own ack flag and batch
            unset($ack, $acked, $acks, $dispatching, $batch, $combine, $flushes);

            if ($this->resetInterval > 0 && 0 === ++$count % $this->resetInterval && $container->has('services_resetter')) {
                $container->get('services_resetter')->reset();
            }

            if (null !== $this->memoryLimit && memory_get_usage(true) >= $this->memoryLimit) {
                $this->flush($bus, $channel, true, $unacks, $heldAcks);
                break;
            }
        }

        return null;
    }

    private function flush(MessageBusInterface $bus, Channel $channel, bool|float $force, DeferredBatchMessageQueue $unacks, ?array &$heldAcks): void
    {
        if (!$unacks->hasPending()) {
            return;
        }

        $toFlush = $unacks->popFlushable($force, microtime(true));

        foreach ($toFlush as $handler) {
            $deferredMessage = $toFlush[$handler];
            $requestId = $deferredMessage->context;
            $envelope = $deferredMessage->envelope;

            $heldAcks = [];
            try {
                $bus->dispatch($envelope->withoutAll(AckStamp::class)->with(new FlushBatchHandlersStamp(false !== $force)));
                self::releaseHeldAcks($channel, $heldAcks, false);
            } catch (\Throwable $e) {
                self::releaseHeldAcks($channel, $heldAcks, true);

                if (!$deferredMessage->acked) {
                    $ackStamp = $envelope->last(AckStamp::class);
                    $envelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($envelope);

                    if ($e instanceof HandlerFailedException) {
                        // a retry must run the batch handlers whose flush failed
                        $failed = $e->getWrappedExceptions();
                        $envelope = $envelope->withoutAll(HandledStamp::class)->with(...array_filter($envelope->all(HandledStamp::class), static fn (HandledStamp $stamp) => !isset($failed[$stamp->getHandlerName()])));
                    }

                    if ($ackStamp) {
                        // the acks that the batch sent during the flush come first, and this failure is ignored when they already gave the message its outcome
                        $ackStamp->ack($envelope, $e);
                    } else {
                        self::sendHandled($channel, $requestId, $envelope, ParallelExecutionFailureSanitizer::sanitizeError($e, $envelope));
                    }
                }

                continue;
            }

            $noAutoAckStamp = $envelope->last(NoAutoAckStamp::class);

            if (!$deferredMessage->acked && !$noAutoAckStamp) {
                self::sendHandled($channel, $requestId, $envelope->withoutAll(AckStamp::class), null);
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

    /**
     * Flushes the batch handlers that still have a failed message in their batch, in a dispatch of their own.
     *
     * Their work for other messages then doesn't depend on the failed dispatch, and their outcome joins the failure.
     */
    private static function flushBatchHandlers(MessageBusInterface $bus, Channel $channel, ?array &$heldAcks, HandlerFailedException $e, array &$acks): HandlerFailedException
    {
        $envelope = $e->getEnvelope();
        $exceptions = $e->getWrappedExceptions();

        $heldAcks = [];
        try {
            $bus->dispatch($envelope->withoutAll(AckStamp::class)->with(new FlushBatchHandlersStamp(true)));
            self::releaseHeldAcks($channel, $heldAcks, false);
        } catch (\Throwable $flushError) {
            // the failed flush may have undone what the batch did, so a retry runs it again
            self::releaseHeldAcks($channel, $heldAcks, true);
            $acks = [];
            $exceptions += $flushError instanceof HandlerFailedException ? $flushError->getWrappedExceptions() : [$flushError];
        }

        $pending = array_map(static fn (NoAutoAckStamp $stamp) => $stamp->getHandlerDescriptor()->getName(), $envelope->all(NoAutoAckStamp::class));
        $stamps = array_filter($envelope->all(HandledStamp::class), static fn (HandledStamp $stamp) => !\in_array($stamp->getHandlerName(), $pending, true));

        foreach ($acks as [$ackEnvelope, $ackError]) {
            if (null === $ackError) {
                $stamps[] = $ackEnvelope->last(HandledStamp::class);
            } elseif ($ackError instanceof HandlerFailedException) {
                $exceptions += $ackError->getWrappedExceptions();
            }
        }

        return new HandlerFailedException($envelope->withoutAll(HandledStamp::class)->with(...$stamps), $exceptions);
    }

    /**
     * Releases the acks that other messages sent during a dispatch, as failures when that dispatch failed.
     *
     * Their batch was flushed during the failed dispatch, so what the batch did for them may have been undone.
     */
    private static function releaseHeldAcks(Channel $channel, ?array &$heldAcks, bool $dispatchFailed): void
    {
        $acks = $heldAcks;
        $heldAcks = null;

        foreach ($acks as [$requestId, $envelope, $e, $combine]) {
            if ($dispatchFailed && null === $e && $stamp = $envelope->last(HandledStamp::class)) {
                $stamps = $envelope->all(HandledStamp::class);
                array_pop($stamps);
                $envelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($envelope->withoutAll(HandledStamp::class)->with(...$stamps));
                $e = new HandlerFailedException($envelope, [$stamp->getHandlerName() => new RuntimeException(\sprintf('The batch of "%s" was flushed during the dispatch of another message, and that dispatch failed.', $stamp->getHandlerName()))]);
            }

            self::sendBatchAck($channel, $combine([[$requestId, $envelope, $e]]));
        }
    }

    /**
     * Sends the outcome of a message once its batch handlers all sent their ack.
     */
    private static function sendBatchAck(Channel $channel, ?array $ack): void
    {
        if (null === $ack) {
            return;
        }

        [$requestId, $envelope, $e] = $ack;

        if (null !== $e) {
            $envelope = ParallelExecutionFailureSanitizer::decorateFailedEnvelope($envelope);
            $e = ParallelExecutionFailureSanitizer::sanitizeError($e, $envelope);
        }

        self::sendHandled($channel, $requestId, $envelope, $e);
    }
}
