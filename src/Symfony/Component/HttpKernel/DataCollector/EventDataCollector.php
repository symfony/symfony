<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Debug\EventDispatcherTraceableInterface;

/**
 * EventDataCollector.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EventDataCollector extends DataCollector
{
    protected $dispatcher;

    public function setEventDispatcher(EventDispatcher $dispatcher)
    {
        if ($dispatcher instanceof EventDispatcherTraceableInterface) {
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
     * @see EventDispatcherTraceableInterface
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
     * @see EventDispatcherTraceableInterface
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
