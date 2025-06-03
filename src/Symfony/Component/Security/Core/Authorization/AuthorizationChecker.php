<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\OfflineTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * AuthorizationChecker is the main authorization point of the Security component.
 *
 * It gives access to the token representing the current user authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthorizationChecker implements AuthorizationCheckerInterface, UserAuthorizationCheckerInterface
{
    private array $tokenStack = [];
    private array $accessDecisionStack = [];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    final public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $token = end($this->tokenStack) ?: $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            $token = new NullToken();
        }
        $accessDecision ??= end($this->accessDecisionStack) ?: new AccessDecision();
        $this->accessDecisionStack[] = $accessDecision;

        try {
            return $accessDecision->isGranted = $this->accessDecisionManager->decide($token, [$attribute], $subject, $accessDecision);
        } finally {
            array_pop($this->accessDecisionStack);
        }
    }

    final public function isGrantedForUser(UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        $token = new class($user->getRoles()) extends AbstractToken implements OfflineTokenInterface {};
        $token->setUser($user);
        $this->tokenStack[] = $token;

        try {
            return $this->isGranted($attribute, $subject, $accessDecision);
        } finally {
            array_pop($this->tokenStack);
        }
    }
}
