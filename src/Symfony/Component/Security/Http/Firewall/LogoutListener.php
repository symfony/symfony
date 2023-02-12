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
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * LogoutListener logout users.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class LogoutListener extends AbstractListener
{
    private TokenStorageInterface $tokenStorage;
    private array $options;
    private HttpUtils $httpUtils;
    private ?CsrfTokenManagerInterface $csrfTokenManager;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * @param array $options An array of options to process a logout attempt
     */
    public function __construct(TokenStorageInterface $tokenStorage, HttpUtils $httpUtils, EventDispatcherInterface $eventDispatcher, array $options = [], CsrfTokenManagerInterface $csrfTokenManager = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->httpUtils = $httpUtils;
        $this->options = array_merge([
            'csrf_parameter' => '_csrf_token',
            'csrf_token_id' => 'logout',
            'logout_path' => '/logout',
        ], $options);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function supports(Request $request): ?bool
    {
        return $this->requiresLogout($request);
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
    public function authenticate(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (null !== $this->csrfTokenManager) {
            $csrfToken = ParameterBagUtils::getRequestParameterValue($request, $this->options['csrf_parameter']);

            if (!\is_string($csrfToken) || false === $this->csrfTokenManager->isTokenValid(new CsrfToken($this->options['csrf_token_id'], $csrfToken))) {
                throw new LogoutException('Invalid CSRF token.');
            }
        }

        $logoutEvent = new LogoutEvent($request, $this->tokenStorage->getToken());
        $this->eventDispatcher->dispatch($logoutEvent);

        if (!$response = $logoutEvent->getResponse()) {
            throw new \RuntimeException('No logout listener set the Response, make sure at least the DefaultLogoutListener is registered.');
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
     */
    protected function requiresLogout(Request $request): bool
    {
        return isset($this->options['logout_path']) && $this->httpUtils->checkRequestPath($request, $this->options['logout_path']);
    }

    public static function getPriority(): int
    {
        return -127;
    }
}
