<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
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
 * LogoutListener logout users.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LogoutListener
{
    protected $securityContext;
    protected $logoutPath;
    protected $targetUrl;

    /**
     * Constructor
     *
     * @param string $logoutPath The path that starts the logout process
     * @param string $targetUrl  The URL to redirect to after logout
     */
    public function __construct(SecurityContext $securityContext, $logoutPath, $targetUrl = '/')
    {
        $this->securityContext = $securityContext;
        $this->logoutPath = $logoutPath;
        $this->targetUrl = $targetUrl;
    }

    /**
     * Registers a core.security listener.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.security', array($this, 'handle'), $priority);
    }

    /**
     * 
     *
     * @param Event $event An Event instance
     */
    public function handle(Event $event)
    {
        $request = $event->get('request');

        if ($this->logoutPath !== $request->getPathInfo()) {
            return;
        }

        $this->securityContext->setToken(null);
        $request->getSession()->invalidate();

        $response = new Response();
        $response->setRedirect(0 !== strpos($this->targetUrl, 'http') ? $request->getUriForPath($this->targetUrl) : $this->targetUrl, 302);

        $event->setReturnValue($response);

        return true;
    }
}
