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

use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
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
        return null !== $token && !$token instanceof NullToken
            // @deprecated since Symfony 5.4, TokenInterface::isAuthenticated() and AnonymousToken no longer exists in 6.0
            && !$token instanceof AnonymousToken && $token->isAuthenticated(false);
    }

    /**
     * {@inheritdoc}
     */
    public function isAnonymous(TokenInterface $token = null/*, $deprecation = true*/)
    {
        if (1 === \func_num_args() || false !== func_get_arg(1)) {
            trigger_deprecation('symfony/security-core', '5.4', 'The "%s()" method is deprecated, use "isAuthenticated()" or "isFullFledged()" if you want to check if the request is (fully) authenticated.', __METHOD__);
        }

        if (null === $token) {
            return false;
        }

        return $token instanceof AnonymousToken || $token instanceof NullToken;
    }

    /**
     * {@inheritdoc}
     */
    public function isRememberMe(TokenInterface $token = null)
    {
        if (null === $token) {
            return false;
        }

        return $token instanceof RememberMeToken;
    }

    /**
     * {@inheritdoc}
     */
    public function isFullFledged(TokenInterface $token = null)
    {
        if (null === $token) {
            return false;
        }

        return !$this->isAnonymous($token, false) && !$this->isRememberMe($token);
    }
}
