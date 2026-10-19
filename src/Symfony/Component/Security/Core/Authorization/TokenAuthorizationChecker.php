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

use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides for the given token, whatever the token storage holds.
 *
 * The checks of the token the authorization checker decides for still go through it, so that its decorators apply.
 *
 * @internal
 */
final class TokenAuthorizationChecker implements AuthorizationCheckerInterface, UserAuthorizationCheckerInterface
{
    public function __construct(
        private readonly TokenInterface $token,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly ?TokenStorageInterface $tokenStorage,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        if ($this->tokenStorage) {
            $token = $this->tokenStorage->getToken();

            // the authorization checker decides for a NullToken when the storage holds no token with a user
            if ($token === $this->token || ($this->token instanceof NullToken && !$token?->getUser())) {
                return $this->authorizationChecker->isGranted($attribute, $subject, $accessDecision);
            }
        }

        return $this->accessDecisionManager->decide($this->token, [$attribute], $subject, $accessDecision);
    }

    public function isGrantedForUser(UserInterface $user, mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        if (!$this->authorizationChecker instanceof UserAuthorizationCheckerInterface) {
            throw new \LogicException(\sprintf('"%s" cannot check the access of another user.', get_debug_type($this->authorizationChecker)));
        }

        return $this->authorizationChecker->isGrantedForUser($user, $attribute, $subject, $accessDecision);
    }
}
