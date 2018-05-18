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
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
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
        usort($messages, function (array $a, array $b): int {
            return $a[1] > $b[1] ? 1 : -1;
        });

        // Keep the messages clones only
        $this->data['messages'] = array_map(function (array $item): Data {
            return $item[0];
        }, $messages);
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
            'envelopeItems' => $tracedMessage['envelopeItems'] ?? null,
            'message' => array(
                'type' => new ClassStub(\get_class($message)),
                'value' => $message,
            ),
        );

        if (array_key_exists('result', $tracedMessage)) {
            $result = $tracedMessage['result'];
            $debugRepresentation['result'] = array(
                'type' => \is_object($result) ? \get_class($result) : gettype($result),
                'value' => $result,
            );
        }

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
        return array_reduce($this->getMessages($bus), function (int $carry, Data $message) {
            return $carry += isset($message['exception']) ? 1 : 0;
        }, 0);
    }

    public function getMessages(string $bus = null): array
    {
        $messages = $this->data['messages'] ?? array();

        return $bus ? array_filter($messages, function (Data $message) use ($bus): bool {
            return $bus === $message['bus'];
        }) : $messages;
    }

    public function getBuses(): array
    {
        return $this->data['buses'];
    }
}
