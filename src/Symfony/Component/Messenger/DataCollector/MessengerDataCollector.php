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
 */
class MessengerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $traceableBuses = array();

    public function registerBus(string $name, TraceableMessageBus $bus)
    {
        $this->traceableBuses[$name] = $bus;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // Noop. Everything is collected live by the traceable buses & cloned as late as possible.
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect()
    {
        $this->data = array('messages' => array(), 'buses' => array_keys($this->traceableBuses));

        $messages = array();
        foreach ($this->traceableBuses as $busName => $bus) {
            foreach ($bus->getDispatchedMessages() as $message) {
                $debugRepresentation = $this->cloneVar($this->collectMessage($busName, $message));
                $messages[] = array($debugRepresentation, $message['callTime']);
            }
        }

        // Order by call time
        usort($messages, function ($a, $b) { return $a[1] <=> $b[1]; });

        // Keep the messages clones only
        $this->data['messages'] = array_column($messages, 0);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'messenger';
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->data = array();
        foreach ($this->traceableBuses as $traceableBus) {
            $traceableBus->reset();
        }
    }

    private function collectMessage(string $busName, array $tracedMessage)
    {
        $message = $tracedMessage['message'];

        $debugRepresentation = array(
            'bus' => $busName,
            'stamps' => $tracedMessage['stamps'] ?? null,
            'message' => array(
                'type' => new ClassStub(\get_class($message)),
                'value' => $message,
            ),
            'caller' => $tracedMessage['caller'],
        );

        if (isset($tracedMessage['exception'])) {
            $exception = $tracedMessage['exception'];

            $debugRepresentation['exception'] = array(
                'type' => \get_class($exception),
                'value' => $exception,
            );
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

    public function getMessages(string $bus = null): iterable
    {
        foreach ($this->data['messages'] ?? array() as $message) {
            if (null === $bus || $bus === $message['bus']) {
                yield $message;
            }
        }
    }

    public function getBuses(): array
    {
        return $this->data['buses'];
    }
}
