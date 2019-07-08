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
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The default implementation of the authentication trust resolver.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticationTrustResolver implements AuthenticationTrustResolverInterface
{
    private $anonymousClass;
    private $rememberMeClass;

    public function __construct(string $anonymousClass = null, string $rememberMeClass = null)
    {
        $this->anonymousClass = $anonymousClass;
        $this->rememberMeClass = $rememberMeClass;

        if (null !== $anonymousClass && !is_a($anonymousClass, AnonymousToken::class, true)) {
            @trigger_error(sprintf('Configuring a custom anonymous token class is deprecated since Symfony 4.2; have the "%s" class extend the "%s" class instead, and remove the "%s" constructor argument.', $anonymousClass, AnonymousToken::class, self::class), E_USER_DEPRECATED);
        }

        if (null !== $rememberMeClass && !is_a($rememberMeClass, RememberMeToken::class, true)) {
            @trigger_error(sprintf('Configuring a custom remember me token class is deprecated since Symfony 4.2; have the "%s" class extend the "%s" class instead, and remove the "%s" constructor argument.', $rememberMeClass, RememberMeToken::class, self::class), E_USER_DEPRECATED);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAnonymous(TokenInterface $token = null)
    {
        if (null === $token) {
            return false;
        }

        if (null !== $this->anonymousClass) {
            return $token instanceof $this->anonymousClass;
        }

        return $token instanceof AnonymousToken;
    }

    /**
     * {@inheritdoc}
     */
    public function isRememberMe(TokenInterface $token = null)
    {
        if (null === $token) {
            return false;
        }

        if (null !== $this->rememberMeClass) {
            return $token instanceof $this->rememberMeClass;
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

        return !$this->isAnonymous($token) && !$this->isRememberMe($token);
    }
}
