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
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * LogoutListener logout users.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LogoutListener implements ListenerInterface
{
    private $securityContext;
    private $options;
    private $handlers;
    private $successHandler;
    private $httpUtils;

    /**
     * Constructor
     *
     * @param SecurityContextInterface      $securityContext
     * @param HttpUtils                     $httpUtils        An HttpUtilsInterface instance
     * @param array                         $options          An array of options for the processing of a logout attempt
     * @param LogoutSuccessHandlerInterface $successHandler
     */
    public function __construct(SecurityContextInterface $securityContext, HttpUtils $httpUtils, array $options = array(), LogoutSuccessHandlerInterface $successHandler = null)
    {
        $this->securityContext = $securityContext;
        $this->httpUtils = $httpUtils;
        $this->options = array_merge(array(
            'logout_path' => '/logout',
            'target_url'  => '/',
        ), $options);
        $this->successHandler = $successHandler;
        $this->handlers = array();
    }

    /**
     * Adds a logout handler
     *
     * @param LogoutHandlerInterface $handler
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

        if (!$this->requiresLogout($request)) {
            return;
        }

        if (null !== $this->successHandler) {
            $response = $this->successHandler->onLogoutSuccess($request);

            if (!$response instanceof Response) {
                throw new \RuntimeException('Logout Success Handler did not return a Response.');
            }
        } else {
            $response = $this->httpUtils->createRedirectResponse($request, $this->options['target_url']);
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

    /**
     * Whether this request is asking for logout.
     *
     * The default implementation only processed requests to a specific path,
     * but a subclass could change this to logout requests where
     * certain parameters is present.
     *
     * @param Request $request
     *
     * @return Boolean
     */
    protected function requiresLogout(Request $request)
    {
        return $this->httpUtils->checkRequestPath($request, $this->options['logout_path']);
    }
}
