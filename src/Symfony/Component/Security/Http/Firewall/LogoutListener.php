<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Events;

/**
 * LogoutListener logout users.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LogoutListener implements ListenerInterface
{
    private $securityContext;
    private $logoutPath;
    private $targetUrl;
    private $handlers;
    private $successHandler;

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
     * Performs the logout if requested
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public function handle(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        if ($this->logoutPath !== $request->getPathInfo()) {
            return;
        }

        if (null !== $this->successHandler) {
            $response = $this->successHandler->onLogoutSuccess($request);

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

        $event->setResponse($response);
    }
}
