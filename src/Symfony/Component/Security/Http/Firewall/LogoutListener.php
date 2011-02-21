<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * LogoutListener logout users.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class LogoutListener implements ListenerInterface
{
    protected $securityContext;
    protected $logoutPath;
    protected $targetUrl;
    protected $handlers;
    protected $successHandler;

    /**
     * Constructor
     *
     * @param SecurityContextInterface $securityContext
     * @param string $logoutPath The path that starts the logout process
     * @param string $targetUrl  The URL to redirect to after logout
     */
    public function __construct(SecurityContextInterface $securityContext, $logoutPath, $targetUrl = '/', LogoutSuccessHandlerInterface $successHandler = null)
    {
        $this->securityContext = $securityContext;
        $this->logoutPath = $logoutPath;
        $this->targetUrl = $targetUrl;
        $this->successHandler = $successHandler;
        $this->handlers = array();
    }

    /**
     * Adds a logout handler
     *
     * @param LogoutHandlerInterface $handler
     * @return void
     */
    public function addHandler(LogoutHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * Registers a core.security listener.
     *
     * @param EventDispatcherInterface $dispatcher An EventDispatcherInterface instance
     * @param integer                  $priority   The priority
     */
    public function register(EventDispatcherInterface $dispatcher)
    {
        $dispatcher->connect('core.security', array($this, 'handle'), 0);
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventDispatcherInterface $dispatcher)
    {
    }

    /**
     * Performs the logout if requested
     *
     * @param EventInterface $event An EventInterface instance
     */
    public function handle(EventInterface $event)
    {
        $request = $event->get('request');

        if ($this->logoutPath !== $request->getPathInfo()) {
            return;
        }

        if (null !== $this->successHandler) {
            $response = $this->successHandler->onLogoutSuccess($event, $request);

            if (!$response instanceof Response) {
                throw new \RuntimeException('Logout Success Handler did not return a Response.');
            }
        } else {
            $response = new RedirectResponse(0 !== strpos($this->targetUrl, 'http') ? $request->getUriForPath($this->targetUrl) : $this->targetUrl, 302);
        }

        // handle multiple logout attempts gracefully
        if ($token = $this->securityContext->getToken()) {
            foreach ($this->handlers as $handler) {
                $handler->logout($request, $response, $token);
            }
        }

        $this->securityContext->setToken(null);

        $event->setProcessed();

        return $response;
    }
}
