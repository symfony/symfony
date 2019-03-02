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

use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * AccessListener enforces access control rules.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AccessListener implements ListenerInterface
{
    private $tokenStorage;

    /**
     * @deprecated since Symfony 4.3
     */
    private $accessDecisionManager;
    private $map;

    /**
     * @deprecated since Symfony 4.3
     */
    private $authManager;
    private $authorizationChecker;

    /**
     * @param AccessDecisionManagerInterface|AccessMapInterface $map
     * @param AccessMapInterface|AuthorizationCheckerInterface  $authorizationChecker
     * @param AuthenticationManagerInterface|null               $authManager
     */
    public function __construct(TokenStorageInterface $tokenStorage, $map, $authorizationChecker, $authManager = null)
    {
        if ($authorizationChecker instanceof AccessMapInterface) {
            @trigger_error(sprintf('Signature "%s()" has changed since Symfony 4.2, now it accepts TokenStorageInterface, AccessMapInterface, AuthorizationCheckerInterface.', __METHOD__), E_USER_DEPRECATED);
            $accessDecisionManager = $map;
            $map = $authorizationChecker;
            $authorizationChecker = null;
        } elseif (!$authorizationChecker instanceof AuthorizationCheckerInterface) {
            throw new \InvalidArgumentException(sprintf('Argument 3 passed to %s() must be an instance of %s or null, %s given.', __METHOD__, AuthorizationCheckerInterface::class, \is_object($authorizationChecker) ? \get_class($authorizationChecker) : \gettype($authorizationChecker)));
        } else {
            $accessDecisionManager = null;
            $authManager = null;
        }

        $this->tokenStorage = $tokenStorage;
        $this->map = $map;
        $this->authorizationChecker = $authorizationChecker;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->authManager = $authManager;
    }

    /**
     * Handles access authorization.
     *
     * @throws AccessDeniedException
     * @throws AuthenticationCredentialsNotFoundException
     */
    public function handle(GetResponseEvent $event)
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            throw new AuthenticationCredentialsNotFoundException('A Token was not found in the TokenStorage.');
        }

        $request = $event->getRequest();

        list($attributes) = $this->map->getPatterns($request);

        if (null === $attributes) {
            return;
        }

        if (null !== $this->authorizationChecker) {
            if (!$this->authorizationChecker->isGranted($attributes, $request)) {
                $exception = new AccessDeniedException();
                $exception->setAttributes($attributes);
                $exception->setSubject($request);

                throw $exception;
            }

            return;
        }

        if (!$token->isAuthenticated()) {
            $token = $this->authManager->authenticate($token);
            $this->tokenStorage->setToken($token);
        }

        if (!$this->accessDecisionManager->decide($token, $attributes, $request)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes($attributes);
            $exception->setSubject($request);

            throw $exception;
        }
    }
}
