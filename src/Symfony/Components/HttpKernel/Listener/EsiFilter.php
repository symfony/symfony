<?php

namespace Symfony\Components\HttpKernel\Listener;

use Symfony\Components\HttpKernel\Response;
use Symfony\Components\HttpKernel\HttpKernelInterface;
use Symfony\Components\EventDispatcher\EventDispatcher;
use Symfony\Components\HttpKernel\Cache\Esi;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * EsiFilter adds a Surrogate-Control HTTP header when the Response needs to be parsed for ESI.
 *
 * @package    Symfony
 * @subpackage Components_HttpKernel
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class EsiFilter
{
    protected $dispatcher;
    protected $esi;

    /**
     * Constructor.
     *
     * @param Symfony\Components\HttpKernel\Cache\Esi $esi An ESI instance
     */
    public function __construct(Esi $esi = null)
    {
        $this->esi = $esi;
    }

    /**
     * Registers a core.response listener to add the Surrogate-Control header to a Response when needed.
     *
     * @param Symfony\Components\EventDispatcher\EventDispatcher $dispatcher An EventDispatcher instance
     */
    public function register(EventDispatcher $dispatcher)
    {
        if (null !== $this->esi)
        {
            $dispatcher->connect('core.response', array($this, 'filter'));
        }
    }

    /**
     * Filters the Response.
     *
     * @param Symfony\Components\EventDispatcher\Event $event    An Event instance
     * @param Symfony\Components\HttpKernel\Response   $response A Response instance
     */
    public function filter($event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getParameter('request_type')) {
            return $response;
        }

        $this->esi->addSurrogateControl($response);

        return $response;
    }
}
