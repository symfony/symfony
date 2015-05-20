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

/**
 * Supports multiple user checkers.
 *
 * This user checker is a collection of other user checkers
 * and triggers each user checker in the sequence provided.
 *
 * @author Iltar van der Berg <ivanderberg@hostnet.nl>
 */
final class ChainUserChecker implements UserCheckerInterface
{
    /**
     * @var UserCheckerInterface[]
     */
    private $userCheckers;

    /**
     * @param UserCheckerInterface[] $userCheckers
     */
    public function __construct(array $userCheckers)
    {
        $this->userCheckers = $userCheckers;
    }

    /**
     * checkPreAuth on all available UserCheckers.
     *
     * {@inheritdoc}
     */
    public function checkPreAuth(UserInterface $user)
    {
        foreach ($this->userCheckers as $userChecker) {
            $userChecker->checkPreAuth($user);
        }
    }

    /**
     * checkPostAuth on all available UserCheckers.
     *
     * {@inheritdoc}
     */
    public function checkPostAuth(UserInterface $user)
    {
        foreach ($this->userCheckers as $userChecker) {
            $userChecker->checkPostAuth($user);
        }
    }
}
