<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication;

use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The default implementation of the authentication trust resolver.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticationTrustResolver implements AuthenticationTrustResolverInterface
{
    public function isAuthenticated(TokenInterface $token = null): bool
    {
        return $token && $token->getUser();
    }

    /**
     * {@inheritdoc}
     */
    public function isRememberMe(TokenInterface $token = null): bool
    {
        return $token && $token instanceof RememberMeToken;
    }

    /**
     * {@inheritdoc}
     */
    public function isFullFledged(TokenInterface $token = null): bool
    {
        return $this->isAuthenticated($token) && !$this->isRememberMe($token);
    }
}
