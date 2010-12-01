<?php

namespace Symfony\Component\HttpKernel\Cache;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * EsiListener adds a Surrogate-Control HTTP header when the Response needs to be parsed for ESI.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EsiListener
{
    protected $dispatcher;
    protected $esi;

    /**
     * Constructor.
     *
     * @param Esi $esi An ESI instance
     */
    public function __construct(Esi $esi = null)
    {
        $this->esi = $esi;
    }

    /**
     * Registers a core.response listener to add the Surrogate-Control header to a Response when needed.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        if (null !== $this->esi)
        {
            $dispatcher->connect('core.response', array($this, 'filter'), $priority);
        }
    }

    /**
     * Filters the Response.
     *
     * @param Event    $event    An Event instance
     * @param Response $response A Response instance
     */
    public function filter($event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return $response;
        }

        $this->esi->addSurrogateControl($response);

        return $response;
    }
}
