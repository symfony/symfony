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
    private $dispatchedMessages = array();

    public function __construct(MessageBusInterface $decoratedBus)
    {
        $this->decoratedBus = $decoratedBus;
    }

    /**
     * {@inheritdoc}
     */
    public function dispatch($message): void
    {
        $envelope = $message instanceof Envelope ? $message : new Envelope($message);
        $context = array(
            'stamps' => array_values($envelope->all()),
            'message' => $envelope->getMessage(),
            'caller' => $this->getCaller(),
            'callTime' => microtime(true),
        );

        try {
            $this->decoratedBus->dispatch($message);
        } catch (\Throwable $e) {
            $context['exception'] = $e;

            throw $e;
        } finally {
            $this->dispatchedMessages[] = $context;
        }
    }

    public function getDispatchedMessages(): array
    {
        return $this->dispatchedMessages;
    }

    public function reset()
    {
        $this->dispatchedMessages = array();
    }

    private function getCaller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);

        $file = $trace[1]['file'];
        $line = $trace[1]['line'];

        for ($i = 2; $i < 8; ++$i) {
            if (isset($trace[$i]['class'], $trace[$i]['function'])
                && 'dispatch' === $trace[$i]['function']
                && is_a($trace[$i]['class'], MessageBusInterface::class, true)
            ) {
                $file = $trace[$i]['file'];
                $line = $trace[$i]['line'];

                while (++$i < 8) {
                    if (isset($trace[$i]['function'], $trace[$i]['file']) && empty($trace[$i]['class']) && 0 !== strpos($trace[$i]['function'], 'call_user_func')) {
                        $file = $trace[$i]['file'];
                        $line = $trace[$i]['line'];

                        break;
                    }
                }
                break;
            }
        }

        $name = str_replace('\\', '/', $file);
        $name = substr($name, strrpos($name, '/') + 1);

        return compact('name', 'file', 'line');
    }
}
