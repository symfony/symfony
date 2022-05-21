<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Token;

use Lcobucci\JWT\Token;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Overrides UserProviderInterface to add $token optional parameter on loadUserByIdentifier method.
 * This is intended for HttpBearerAuthenticator.
 *
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 */
interface TokenAwareUserProviderInterface extends UserProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function loadUserByIdentifier(string $identifier, Token $token = null): UserInterface;
}
