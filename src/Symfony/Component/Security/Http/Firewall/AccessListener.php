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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Event\LazyResponseEvent;

/**
 * AccessListener enforces access control rules.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class AccessListener extends AbstractListener
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
     * {@inheritdoc}
     */
    public function supports(Request $request): ?bool
    {
        [$attributes] = $this->map->getPatterns($request);
        $request->attributes->set('_access_control_attributes', $attributes);

        return $attributes && [AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY] !== $attributes ? true : null;
    }

    /**
     * Handles access authorization.
     *
     * @throws AccessDeniedException
     * @throws AuthenticationCredentialsNotFoundException
     */
    public function authenticate(RequestEvent $event)
    {
        if (!$event instanceof LazyResponseEvent && null === $token = $this->tokenStorage->getToken()) {
            throw new AuthenticationCredentialsNotFoundException('A Token was not found in the TokenStorage.');
        }

        $request = $event->getRequest();

        $attributes = $request->attributes->get('_access_control_attributes');
        $request->attributes->remove('_access_control_attributes');

        if (!$attributes || ([AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY] === $attributes && $event instanceof LazyResponseEvent)) {
            return;
        }

        if ($event instanceof LazyResponseEvent && null === $token = $this->tokenStorage->getToken()) {
            throw new AuthenticationCredentialsNotFoundException('A Token was not found in the TokenStorage.');
        }

        if (!$token->isAuthenticated()) {
            $token = $this->authManager->authenticate($token);
            $this->tokenStorage->setToken($token);
        }

        if (!$this->accessDecisionManager->decide($token, $attributes, $request, true)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes($attributes);
            $exception->setSubject($request);

            throw $exception;
        }
    }
}
