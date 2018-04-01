<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\DataCollector;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\DataCollector\DataCollector;
use Symphony\Component\Messenger\MiddlewareInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class MessengerDataCollector extends DataCollector implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        return $this->data;
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

    /**
     * {@inheritdoc}
     */
    public function handle($message, callable $next)
    {
        $debugRepresentation = array(
            'message' => array(
                'type' => get_class($message),
                'object' => $this->cloneVar($message),
            ),
        );

        $exception = null;
        try {
            $result = $next($message);

            if (is_object($result)) {
                $debugRepresentation['result'] = array(
                    'type' => get_class($result),
                    'object' => $this->cloneVar($result),
                );
            } elseif (is_array($result)) {
                $debugRepresentation['result'] = array(
                    'type' => 'array',
                    'object' => $this->cloneVar($result),
                );
            } else {
                $debugRepresentation['result'] = array(
                    'type' => gettype($result),
                    'value' => $result,
                );
            }
        } catch (\Throwable $exception) {
            $debugRepresentation['exception'] = array(
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            );
        }

        $this->data['messages'][] = $debugRepresentation;

        if (null !== $exception) {
            throw $exception;
        }

        return $result;
    }

    public function getMessages(): array
    {
        return $this->data['messages'] ?? array();
    }
}
