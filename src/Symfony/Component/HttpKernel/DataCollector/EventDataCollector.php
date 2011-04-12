<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\TraceableEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * EventDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EventDataCollector extends DataCollector
{
    private $dispatcher;

    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        if ($dispatcher instanceof TraceableEventDispatcherInterface) {
            $this->dispatcher = $dispatcher;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $this->data = array(
            'called_listeners'     => null !== $this->dispatcher ? $this->dispatcher->getCalledListeners() : array(),
            'not_called_listeners' => null !== $this->dispatcher ? $this->dispatcher->getNotCalledListeners() : array(),
        );
    }

    /**
     * Gets the called listeners.
     *
     * @return array An array of called listeners
     *
     * @see TraceableEventDispatcherInterface
     */
    public function getCalledListeners()
    {
        return $this->data['called_listeners'];
    }

    /**
     * Gets the not called listeners.
     *
     * @return array An array of not called listeners
     *
     * @see TraceableEventDispatcherInterface
     */
    public function getNotCalledListeners()
    {
        return $this->data['not_called_listeners'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'events';
    }
}
