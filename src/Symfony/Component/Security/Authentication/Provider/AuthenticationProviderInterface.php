<?php

namespace Symfony\Component\Security\Authentication\Provider;

use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Authentication\AuthenticationManagerInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * AuthenticationProviderInterface is the interface for for all authentication
 * providers.
 *
 * Concrete implementations processes specific Token instances.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface AuthenticationProviderInterface extends AuthenticationManagerInterface
{
    /**
     * Checks whether this provider supports the given token.
     *
     * @param TokenInterface $token A TokenInterface instance
     *
     * @return Boolean true if the implementation supports the Token, false otherwise
     */
     function supports(TokenInterface $token);
}
