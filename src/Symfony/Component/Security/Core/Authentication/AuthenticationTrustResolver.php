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
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

/**
 * The default implementation of the authentication trust resolver.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since symfony/security-core 5.1
 */
class AuthenticationTrustResolver implements AuthenticationTrustResolverInterface
{
    public function __construct(bool $triggerDeprecation = true)
    {
        if ($triggerDeprecation) {
            trigger_deprecation('symfony/security-core', '5.1', '%s is deprecated.', __CLASS__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAnonymous(TokenInterface $token = null)
    {
        trigger_deprecation('symfony/security-core', '5.1', 'The %s is deprecated, use %s::isGranted("IS_ANONYMOUS") instead.', __CLASS__, AuthorizationChecker::class);

        if (null === $token) {
            return false;
        }

        return $token instanceof AnonymousToken;
    }

    /**
     * {@inheritdoc}
     */
    public function isRememberMe(TokenInterface $token = null)
    {
        trigger_deprecation('symfony/security-core', '5.1', 'The %s is deprecated, use %s::isGranted("IS_REMEMBERED") instead.', __CLASS__, AuthorizationChecker::class);

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
        trigger_deprecation('symfony/security-core', '5.1', 'The %s is deprecated, use %s::isGranted("IS_AUTHENTICATED_FULLY") instead.', __CLASS__, AuthorizationChecker::class);

        if (null === $token) {
            return false;
        }

        return !$this->isAnonymous($token) && !$this->isRememberMe($token);
    }
}
