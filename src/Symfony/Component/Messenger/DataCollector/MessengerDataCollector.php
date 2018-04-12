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
use Symfony\Component\Messenger\MiddlewareInterface;

/**
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.1
 */
class MessengerDataCollector extends DataCollector implements MiddlewareInterface
{
    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        // noop
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
                'type' => \get_class($message),
                'object' => $this->cloneVar($message),
            ),
        );

        $exception = null;
        try {
            $result = $next($message);

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
        } catch (\Throwable $exception) {
            $debugRepresentation['exception'] = array(
                'type' => \get_class($exception),
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
