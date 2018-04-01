<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Firewall;

use Symphony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symphony\Component\Security\Http\AccessMapInterface;
use Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symphony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * AccessListener enforces access control rules.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class AccessListener implements ListenerInterface
{
    private $tokenStorage;
    private $accessDecisionManager;
    private $map;
    private $authManager;

    public function __construct(TokenStorageInterface $tokenStorage, AccessDecisionManagerInterface $accessDecisionManager, AccessMapInterface $map, AuthenticationManagerInterface $authManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->map = $map;
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
