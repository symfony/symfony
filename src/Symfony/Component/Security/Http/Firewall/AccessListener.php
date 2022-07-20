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
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Authentication\NoopAuthenticationManager;
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
    private $exceptionOnNoToken;

    public function __construct(TokenStorageInterface $tokenStorage, AccessDecisionManagerInterface $accessDecisionManager, AccessMapInterface $map, /* bool */ $exceptionOnNoToken = true)
    {
        if ($exceptionOnNoToken instanceof AuthenticationManagerInterface) {
            trigger_deprecation('symfony/security-http', '5.4', 'The $authManager argument of "%s" is deprecated.', __METHOD__);
            $authManager = $exceptionOnNoToken;
            $exceptionOnNoToken = \func_num_args() > 4 ? func_get_arg(4) : true;
        }

        if (false !== $exceptionOnNoToken) {
            trigger_deprecation('symfony/security-http', '5.4', 'Not setting the $exceptionOnNoToken argument of "%s" to "false" is deprecated.', __METHOD__);
        }

        $this->tokenStorage = $tokenStorage;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->map = $map;
        $this->authManager = $authManager ?? (class_exists(AuthenticationManagerInterface::class) ? new NoopAuthenticationManager() : null);
        $this->exceptionOnNoToken = $exceptionOnNoToken;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): ?bool
    {
        [$attributes] = $this->map->getPatterns($request);
        $request->attributes->set('_access_control_attributes', $attributes);

        if ($attributes && (
            (\defined(AuthenticatedVoter::class.'::IS_AUTHENTICATED_ANONYMOUSLY') ? [AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY] !== $attributes : true)
            && [AuthenticatedVoter::PUBLIC_ACCESS] !== $attributes
        )) {
            return true;
        }

        return null;
    }

    /**
     * Handles access authorization.
     *
     * @throws AccessDeniedException
     * @throws AuthenticationCredentialsNotFoundException when the token storage has no authentication token and $exceptionOnNoToken is set to true
     */
    public function authenticate(RequestEvent $event)
    {
        if (!$event instanceof LazyResponseEvent && null === ($token = $this->tokenStorage->getToken()) && $this->exceptionOnNoToken) {
            throw new AuthenticationCredentialsNotFoundException('A Token was not found in the TokenStorage.');
        }

        $request = $event->getRequest();

        $attributes = $request->attributes->get('_access_control_attributes');
        $request->attributes->remove('_access_control_attributes');

        if (!$attributes || ((
            (\defined(AuthenticatedVoter::class.'::IS_AUTHENTICATED_ANONYMOUSLY') ? [AuthenticatedVoter::IS_AUTHENTICATED_ANONYMOUSLY] === $attributes : false)
            || [AuthenticatedVoter::PUBLIC_ACCESS] === $attributes
        ) && $event instanceof LazyResponseEvent)) {
            return;
        }

        if ($event instanceof LazyResponseEvent) {
            $token = $this->tokenStorage->getToken();
        }

        if (null === $token) {
            if ($this->exceptionOnNoToken) {
                throw new AuthenticationCredentialsNotFoundException('A Token was not found in the TokenStorage.');
            }

            $token = new NullToken();
        }

        // @deprecated since Symfony 5.4
        if (method_exists($token, 'isAuthenticated') && !$token->isAuthenticated(false)) {
            trigger_deprecation('symfony/core', '5.4', 'Returning false from "%s()" is deprecated, return null from "getUser()" instead.');

            if ($this->authManager) {
                $token = $this->authManager->authenticate($token);
                $this->tokenStorage->setToken($token);
            }
        }

        if (!$this->accessDecisionManager->decide($token, $attributes, $request, true)) {
            throw $this->createAccessDeniedException($request, $attributes);
        }
    }

    private function createAccessDeniedException(Request $request, array $attributes)
    {
        $exception = new AccessDeniedException();
        $exception->setAttributes($attributes);
        $exception->setSubject($request);

        return $exception;
    }

    public static function getPriority(): int
    {
        return -255;
    }
}
