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
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
class MessengerDataCollector extends DataCollector
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
        $this->data = array('messages' => array());

        foreach ($this->traceableBuses as $busName => $bus) {
            foreach ($bus->getDispatchedMessages() as $message) {
                $this->data['messages'][] = $this->collectMessage($busName, $message);
            }
        }
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
    }

    private function collectMessage(string $busName, array $tracedMessage)
    {
        $message = $tracedMessage['message'];

        $debugRepresentation = array(
            'bus' => $busName,
            'message' => array(
                'type' => \get_class($message),
                'object' => $this->cloneVar($message),
            ),
        );

        if (array_key_exists('result', $tracedMessage)) {
            $result = $tracedMessage['result'];

            if (\is_object($result)) {
                $debugRepresentation['result'] = array(
                    'type' => \get_class($result),
                    'object' => $this->cloneVar($result),
                );
            } elseif (\is_array($result)) {
                $debugRepresentation['result'] = array(
                    'type' => 'array',
                    'object' => $this->cloneVar($result),
                );
            } else {
                $debugRepresentation['result'] = array(
                    'type' => \gettype($result),
                    'value' => $result,
                );
            }
        }

        if (isset($tracedMessage['exception'])) {
            $exception = $tracedMessage['exception'];

            $debugRepresentation['exception'] = array(
                'type' => \get_class($exception),
                'message' => $exception->getMessage(),
            );
        }

        return $debugRepresentation;
    }

    public function getMessages(): array
    {
        return $this->data['messages'] ?? array();
    }
}
