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

/**
 * AuthorizationChecker is the main authorization point of the Security component.
 *
 * It gives access to the token representing the current user authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthorizationChecker implements AuthorizationCheckerInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    final public function isGranted(mixed $attribute, mixed $subject = null): bool
    {
        return $this->getDecision($attribute, $subject)->isGranted();
    }

    final public function getDecision($attribute, $subject = null): AccessDecision
    {
        $token = $this->tokenStorage->getToken();

        if (!$token || !$token->getUser()) {
            $token = new NullToken();
        }

        if (!method_exists($this->accessDecisionManager, 'getDecision')) {
            trigger_deprecation('symfony/security-core', '7.1', 'Not implementing "%s::getDecision()" method is deprecated, and would be required in 7.0.', \get_class($this->accessDecisionManager));

            return $this->accessDecisionManager->decide($token, [$attribute], $subject) ? AccessDecision::createGranted() : AccessDecision::createDenied();
        }

        return $this->accessDecisionManager->getDecision($token, [$attribute], $subject);
    }
}
