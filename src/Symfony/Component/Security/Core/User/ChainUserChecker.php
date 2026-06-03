<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\User;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class ChainUserChecker implements UserCheckerInterface
{
    /**
     * @param iterable<UserCheckerInterface> $checkers
     */
    public function __construct(private readonly iterable $checkers)
    {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        foreach ($this->checkers as $checker) {
            $checker->checkPreAuth($user);
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        foreach ($this->checkers as $checker) {
            $checker->checkPostAuth($user, $token);
        }
    }
}
