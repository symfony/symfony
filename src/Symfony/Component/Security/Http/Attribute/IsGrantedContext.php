<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Attribute;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\User\UserInterface;

class IsGrantedContext implements AuthorizationCheckerInterface
{
    public function __construct(
        public readonly TokenInterface $token,
        public readonly ?UserInterface $user,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        return $this->authorizationChecker->isGranted($attribute, $subject, $accessDecision);
    }

    public function isAuthenticated(): bool
    {
        return $this->authorizationChecker->isGranted(AuthenticatedVoter::IS_AUTHENTICATED);
    }

    public function isAuthenticatedFully(): bool
    {
        return $this->authorizationChecker->isGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY);
    }

    public function isImpersonator(): bool
    {
        return $this->authorizationChecker->isGranted(AuthenticatedVoter::IS_IMPERSONATOR);
    }
}
