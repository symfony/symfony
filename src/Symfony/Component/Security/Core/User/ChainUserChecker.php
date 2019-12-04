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

use InvalidArgumentException;

/**
 * Calls multiple user checkers until the first exception is thrown.
 *
 * @author Markus Poerschke <markus@poerschke.nrw>
 */
class ChainUserChecker implements UserCheckerInterface
{
    /**
     * @var PreAuthUserCheckerInterface[]
     */
    private $preAuthCheckers = [];

    /**
     * @var PostAuthUserCheckerInterface[]
     */
    private $postAuthCheckers = [];

    public function checkPreAuth(UserInterface $user)
    {
        foreach ($this->preAuthCheckers as $checker) {
            $checker->checkPreAuth($user);
        }
    }

    public function checkPostAuth(UserInterface $user)
    {
        foreach ($this->postAuthCheckers as $checker) {
            $checker->checkPostAuth($user);
        }
    }

    /**
     * @param PreAuthUserCheckerInterface|PostAuthUserCheckerInterface $checker
     */
    public function add(object $checker)
    {
        $implementsUserCheckerInterface = false;

        if ($checker instanceof PreAuthUserCheckerInterface) {
            $this->preAuthCheckers[] = $checker;
            $implementsUserCheckerInterface = true;
        }

        if ($checker instanceof PostAuthUserCheckerInterface) {
            $this->postAuthCheckers[] = $checker;
            $implementsUserCheckerInterface = true;
        }

        if (false === $implementsUserCheckerInterface) {
            throw new InvalidArgumentException('The checker must be of instance PreAuthUserCheckerInterface or PostAuthUserCheckerInterface');
        }
    }
}
