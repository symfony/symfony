<?php

namespace Symfony\Components\HttpKernel\Profiler;

use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\EventDispatcher\Event;
use Symfony\Components\HttpFoundation\Response;
use Symfony\Components\HttpKernel\HttpKernelInterface;

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ProfilerListener
{
    protected $profiler;

    public function __construct(Profiler $profiler)
    {
        $this->profiler = $profiler;
    }

    /**
     * Registers a core.response listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     */
    public function register(EventDispatcher $dispatcher)
    {
        $dispatcher->connect('core.response', array($this, 'handle'));
    }

    public function handle(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getParameter('request_type')) {
            return $response;
        }

        $this->profiler->collect($response);

        return $response;
    }
}
