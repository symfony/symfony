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

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class TraceableMessageBus implements MessageBusInterface
{
    private $decoratedBus;
    private $dispatchedMessages = [];

    public function __construct(MessageBusInterface $decoratedBus)
    {
        $this->decoratedBus = $decoratedBus;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $context = [
            'stamps' => array_merge([], ...array_values($envelope->all())),
            'message' => $envelope->getMessage(),
            'caller' => $this->getCaller(),
            'callTime' => microtime(true),
        ];

        try {
            return $envelope = $this->decoratedBus->dispatch($message, $stamps);
        } catch (\Throwable $e) {
            $context['exception'] = $e;

            throw $e;
        } finally {
            $this->dispatchedMessages[] = $context + ['stamps_after_dispatch' => array_merge([], ...array_values($envelope->all()))];
        }
    }

    public function getDispatchedMessages(): array
    {
        return $this->dispatchedMessages;
    }

    public function reset()
    {
        $this->dispatchedMessages = [];
    }

    private function getCaller(): array
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 8);

        $file = $trace[1]['file'] ?? null;
        $line = $trace[1]['line'] ?? null;

        $handleTraitFile = (new \ReflectionClass(HandleTrait::class))->getFileName();
        $found = false;
        for ($i = 1; $i < 8; ++$i) {
            if (isset($trace[$i]['file'], $trace[$i + 1]['file'], $trace[$i + 1]['line']) && $trace[$i]['file'] === $handleTraitFile) {
                $file = $trace[$i + 1]['file'];
                $line = $trace[$i + 1]['line'];
                $found = true;

                break;
            }
        }

        for ($i = 2; $i < 8 && !$found; ++$i) {
            if (isset($trace[$i]['class'], $trace[$i]['function'])
                && 'dispatch' === $trace[$i]['function']
                && is_a($trace[$i]['class'], MessageBusInterface::class, true)
            ) {
                $file = $trace[$i]['file'];
                $line = $trace[$i]['line'];

                while (++$i < 8) {
                    if (isset($trace[$i]['function'], $trace[$i]['file']) && empty($trace[$i]['class']) && !str_starts_with($trace[$i]['function'], 'call_user_func')) {
                        $file = $trace[$i]['file'];
                        $line = $trace[$i]['line'];

                        break;
                    }
                }
                break;
            }
        }

        $name = str_replace('\\', '/', (string) $file);

        return [
            'name' => substr($name, strrpos($name, '/') + 1),
            'file' => $file,
            'line' => $line,
        ];
    }
}
