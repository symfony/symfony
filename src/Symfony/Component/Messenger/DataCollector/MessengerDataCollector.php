<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Messenger\TraceableMessageBus;
use Symfony\Component\VarDumper\Caster\ClassStub;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @final
 */
class MessengerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private array $traceableBuses = [];

    public function registerBus(string $name, TraceableMessageBus $bus)
    {
        $this->traceableBuses[$name] = $bus;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        // Noop. Everything is collected live by the traceable buses & cloned as late as possible.
    }

    public function lateCollect()
    {
        $this->data = ['messages' => [], 'buses' => array_keys($this->traceableBuses)];

        $messages = [];
        foreach ($this->traceableBuses as $busName => $bus) {
            foreach ($bus->getDispatchedMessages() as $message) {
                $debugRepresentation = $this->cloneVar($this->collectMessage($busName, $message));
                $messages[] = [$debugRepresentation, $message['callTime']];
            }
        }

        // Order by call time
        usort($messages, function ($a, $b) { return $a[1] <=> $b[1]; });

        // Keep the messages clones only
        $this->data['messages'] = array_column($messages, 0);
    }

    public function getName(): string
    {
        return 'messenger';
    }

    public function reset()
    {
        $this->data = [];
        foreach ($this->traceableBuses as $traceableBus) {
            $traceableBus->reset();
        }
    }

    protected function getCasters(): array
    {
        $casters = parent::getCasters();

        // Unset the default caster truncating collectors data.
        unset($casters['*']);

        return $casters;
    }

    private function collectMessage(string $busName, array $tracedMessage)
    {
        $message = $tracedMessage['message'];

        $debugRepresentation = [
            'bus' => $busName,
            'stamps' => $tracedMessage['stamps'] ?? null,
            'stamps_after_dispatch' => $tracedMessage['stamps_after_dispatch'] ?? null,
            'message' => [
                'type' => new ClassStub($message::class),
                'value' => $message,
            ],
            'caller' => $tracedMessage['caller'],
        ];

        if (isset($tracedMessage['exception'])) {
            $exception = $tracedMessage['exception'];

            $debugRepresentation['exception'] = [
                'type' => $exception::class,
                'value' => $exception,
            ];
        }

        return $debugRepresentation;
    }

    public function getExceptionsCount(string $bus = null): int
    {
        $count = 0;
        foreach ($this->getMessages($bus) as $message) {
            $count += (int) isset($message['exception']);
        }

        return $count;
    }

    public function getMessages(string $bus = null): array
    {
        if (null === $bus) {
            return $this->data['messages'];
        }

        return array_filter($this->data['messages'], function ($message) use ($bus) {
            return $bus === $message['bus'];
        });
    }

    public function getBuses(): array
    {
        return $this->data['buses'];
    }
}
