<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication\Provider;

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" interface is deprecated, use the new authenticator system instead.', AuthenticationProviderInterface::class);

/**
 * AuthenticationProviderInterface is the interface for all authentication
 * providers.
 *
 * Concrete implementations processes specific Token instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
interface AuthenticationProviderInterface extends AuthenticationManagerInterface
{
    /**
     * Use this constant for not provided username.
     *
     * @var string
     */
    public const USERNAME_NONE_PROVIDED = 'NONE_PROVIDED';

    /**
     * Checks whether this provider supports the given token.
     *
     * @return bool true if the implementation supports the Token, false otherwise
     */
    public function supports(TokenInterface $token);
}
