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

use Symfony\Component\Security\Core\Authentication\Token\UserAuthorizationCheckerToken;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @author Nate Wiebe <nate@northern.co>
 */
final class UserAuthorizationChecker implements UserAuthorizationCheckerInterface
{
    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    public function isGrantedForUser(UserInterface $user, mixed $attribute, mixed $subject = null): bool
    {
        return $this->accessDecisionManager->decide(new UserAuthorizationCheckerToken($user), [$attribute], $subject);
    }
}
