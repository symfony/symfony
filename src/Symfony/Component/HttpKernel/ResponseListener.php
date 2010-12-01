<?php

namespace Symfony\Component\HttpKernel;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ResponseListener fixes the Response Content-Type.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ResponseListener
{
    /**
     * Registers a core.response listener to change the Content-Type header based on the Request format.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.response', array($this, 'filter'), $priority);
    }

    /**
     * Filters the Response.
     *
     * @param Event    $event    An Event instance
     * @param Response $response A Response instance
     */
    public function filter(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type') || $response->headers->has('Content-Type')) {
            return $response;
        }

        $request = $event->get('request');
        $format = $request->getRequestFormat();
        if ((null !== $format) && $mimeType = $request->getMimeType($format)) {
            $response->headers->set('Content-Type', $mimeType);
        }

        return $response;
    }
}
