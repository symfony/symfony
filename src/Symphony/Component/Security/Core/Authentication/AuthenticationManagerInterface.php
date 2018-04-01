<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Authentication;

use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Exception\AuthenticationException;

/**
 * AuthenticationManagerInterface is the interface for authentication managers,
 * which process Token authentication.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface AuthenticationManagerInterface
{
    /**
     * Attempts to authenticate a TokenInterface object.
     *
     * @param TokenInterface $token The TokenInterface instance to authenticate
     *
     * @return TokenInterface An authenticated TokenInterface instance, never null
     *
     * @throws AuthenticationException if the authentication fails
     */
    public function authenticate(TokenInterface $token);
}
