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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The default implementation of the authentication trust resolver.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @since v2.0.0
 */
class AuthenticationTrustResolver implements AuthenticationTrustResolverInterface
{
    private $anonymousClass;
    private $rememberMeClass;

    /**
     * Constructor
     *
     * @param string $anonymousClass
     * @param string $rememberMeClass
     *
     * @since v2.0.0
     */
    public function __construct($anonymousClass, $rememberMeClass)
    {
        $this->anonymousClass = $anonymousClass;
        $this->rememberMeClass = $rememberMeClass;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function isAnonymous(TokenInterface $token = null)
    {
        if (null === $token) {
            return false;
        }

        return $token instanceof $this->anonymousClass;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function isRememberMe(TokenInterface $token = null)
    {
        if (null === $token) {
            return false;
        }

        return $token instanceof $this->rememberMeClass;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function isFullFledged(TokenInterface $token = null)
    {
        if (null === $token) {
            return false;
        }

        return !$this->isAnonymous($token) && !$this->isRememberMe($token);
    }
}
