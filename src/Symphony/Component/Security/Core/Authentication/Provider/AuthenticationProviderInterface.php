<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Core\Authentication\Provider;

use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\Authentication\AuthenticationManagerInterface;

/**
 * AuthenticationProviderInterface is the interface for all authentication
 * providers.
 *
 * Concrete implementations processes specific Token instances.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface AuthenticationProviderInterface extends AuthenticationManagerInterface
{
    /**
     * Use this constant for not provided username.
     *
     * @var string
     */
    const USERNAME_NONE_PROVIDED = 'NONE_PROVIDED';

    /**
     * Checks whether this provider supports the given token.
     *
     * @return bool true if the implementation supports the Token, false otherwise
     */
    public function supports(TokenInterface $token);
}
