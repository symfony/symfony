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

use Symfony\Component\Security\Core\Exception\AccountStatusException;

/**
 * @see UserCheckerInterface
 *
 * @author Markus Poerschke <markus@poerschke.nrw>
 */
interface PostAuthUserCheckerInterface
{
    /**
     * Checks the user account after authentication.
     *
     * @throws AccountStatusException
     */
    public function checkPostAuth(UserInterface $user);
}
