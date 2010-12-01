<?php

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ProfilerListener collects data for the current request by listening to the core.response event.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ProfilerListener
{
    protected $profiler;
    protected $exception;
    protected $onlyException;
    protected $matcher;

    /**
     * Constructor.
     *
     * @param Profiler                $profiler      A Profiler instance
     * @param RequestMatcherInterface $matcher       A RequestMatcher instance
     * @param Boolean                 $onlyException true if the profiler only collects data when an exception occurs, false otherwise
     */
    public function __construct(Profiler $profiler, RequestMatcherInterface $matcher = null, $onlyException = false)
    {
        $this->profiler = $profiler;
        $this->matcher = $matcher;
        $this->onlyException = $onlyException;
    }

    /**
     * Registers a core.response and core.exception listeners.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.exception', array($this, 'handleException'), $priority);
        $dispatcher->connect('core.response', array($this, 'handleResponse'), $priority);
    }

    /**
     * Handles the core.exception event.
     *
     * @param Event $event An Event instance
     */
    public function handleException(Event $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return false;
        }

        $this->exception = $event->get('exception');

        return false;
    }

    /**
     * Handles the core.response event.
     *
     * @param Event $event An Event instance
     *
     * @return Response $response A Response instance
     */
    public function handleResponse(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return $response;
        }

        if (null !== $this->matcher && !$this->matcher->matches($event->get('request'))) {
            return $response;
        }

        if ($this->onlyException && null === $this->exception) {
            return $response;
        }

        $this->profiler->collect($event->get('request'), $response, $this->exception);
        $this->exception = null;

        return $response;
    }
}
