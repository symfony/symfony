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
use Symfony\Component\Messenger\Stamp\ProfilerTokenStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
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

    public function registerBus(string $name, TraceableMessageBus $bus): void
    {
        $this->traceableBuses[$name] = $bus;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // Noop. Everything is collected live by the traceable buses & cloned as late as possible.
    }

    public function lateCollect(): void
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
        usort($messages, static fn ($a, $b) => $a[1] <=> $b[1]);

        // Keep the messages clones only
        $this->data['messages'] = array_column($messages, 0);
    }

    public function getName(): string
    {
        return 'messenger';
    }

    public function reset(): void
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

    private function collectMessage(string $busName, array $tracedMessage): array
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

        if (null !== $token = $this->getSentProfilerToken($tracedMessage)) {
            $debugRepresentation['profiler_token'] = $token;
            $debugRepresentation['message_class'] = $message::class;
        }

        if (isset($tracedMessage['exception'])) {
            $exception = $tracedMessage['exception'];

            $debugRepresentation['exception'] = [
                'type' => $exception::class,
                'value' => $exception,
            ];
        }

        return $debugRepresentation;
    }

    /**
     * Returns the token stamped on a message sent to transports, which the profiles of the workers handling it refer to.
     */
    private function getSentProfilerToken(array $tracedMessage): ?string
    {
        $countSentStamps = static fn (array $stamps): int => \count(array_filter($stamps, static fn ($stamp) => $stamp instanceof SentStamp));

        // a received message is sent again when relayed by an outbox or redispatched, but not when a sync transport handles it
        if ($countSentStamps($tracedMessage['stamps_after_dispatch'] ?? []) <= $countSentStamps($tracedMessage['stamps'] ?? [])) {
            return null;
        }

        $token = null;
        foreach ($tracedMessage['stamps_after_dispatch'] as $stamp) {
            if ($stamp instanceof ProfilerTokenStamp) {
                $token = $stamp->getToken();
            }
        }

        return $token;
    }

    public function getExceptionsCount(?string $bus = null): int
    {
        $count = 0;
        foreach ($this->getMessages($bus) as $message) {
            $count += (int) isset($message['exception']);
        }

        return $count;
    }

    public function getMessages(?string $bus = null): array
    {
        if (null === $bus) {
            return $this->data['messages'];
        }

        return array_filter($this->data['messages'], static fn ($message) => $bus === $message['bus']);
    }

    public function getBuses(): array
    {
        return $this->data['buses'];
    }
}
