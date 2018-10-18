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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\LogoutHandlerInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;
use Symfony\Component\Security\Http\ParameterBagUtils;

/**
 * LogoutListener logout users.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since Symfony 4.3
 */
class LogoutListener implements ListenerInterface
{
    use LegacyListenerTrait;

    private $tokenStorage;
    private $options;
    private $handlers;
    private $successHandler;
    private $httpUtils;
    private $csrfTokenManager;

    /**
     * @param TokenStorageInterface          $tokenStorage
     * @param HttpUtils                      $httpUtils        An HttpUtils instance
     * @param LogoutSuccessHandlerInterface  $successHandler   A LogoutSuccessHandlerInterface instance
     * @param array                          $options          An array of options to process a logout attempt
     * @param CsrfTokenManagerInterface|null $csrfTokenManager A CsrfTokenManagerInterface instance
     */
    public function __construct(TokenStorageInterface $tokenStorage, HttpUtils $httpUtils, LogoutSuccessHandlerInterface $successHandler, array $options = [], CsrfTokenManagerInterface $csrfTokenManager = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->httpUtils = $httpUtils;
        $this->options = array_merge([
            'csrf_parameter' => '_csrf_token',
            'csrf_token_id' => 'logout',
            'logout_path' => '/logout',
        ], $options);
        $this->successHandler = $successHandler;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->handlers = [];
    }

    public function addHandler(LogoutHandlerInterface $handler)
    {
        $this->handlers[] = $handler;
    }

    /**
     * Performs the logout if requested.
     *
     * If a CsrfTokenManagerInterface instance is available, it will be used to
     * validate the request.
     *
     * @throws LogoutException   if the CSRF token is invalid
     * @throws \RuntimeException if the LogoutSuccessHandlerInterface instance does not return a response
     */
    public function __invoke(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (!$this->requiresLogout($request)) {
            return;
        }

        if (null !== $this->csrfTokenManager) {
            $csrfToken = ParameterBagUtils::getRequestParameterValue($request, $this->options['csrf_parameter']);

            if (false === $this->csrfTokenManager->isTokenValid(new CsrfToken($this->options['csrf_token_id'], $csrfToken))) {
                throw new LogoutException('Invalid CSRF token.');
            }
        }

        $response = $this->successHandler->onLogoutSuccess($request);
        if (!$response instanceof Response) {
            throw new \RuntimeException('Logout Success Handler did not return a Response.');
        }

        // handle multiple logout attempts gracefully
        if ($token = $this->tokenStorage->getToken()) {
            foreach ($this->handlers as $handler) {
                $handler->logout($request, $response, $token);
            }
        }

        $this->tokenStorage->setToken(null);

        $event->setResponse($response);
    }

    /**
     * Whether this request is asking for logout.
     *
     * The default implementation only processed requests to a specific path,
     * but a subclass could change this to logout requests where
     * certain parameters is present.
     *
     * @return bool
     */
    protected function requiresLogout(Request $request)
    {
        return isset($this->options['logout_path']) && $this->httpUtils->checkRequestPath($request, $this->options['logout_path']);
    }
}
