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

    public function checkPostAuth(UserInterface $user /*, TokenInterface $token*/): void
    {
        $token = 1 < \func_num_args() ? func_get_arg(1) : null;

        foreach ($this->checkers as $checker) {
            if ($token instanceof TokenInterface) {
                $checker->checkPostAuth($user, $token);
            } else {
                $checker->checkPostAuth($user);
            }
        }
    }
}
