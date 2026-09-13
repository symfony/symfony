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

use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;

/**
 * The default implementation of the authentication trust resolver.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticationTrustResolver implements AuthenticationTrustResolverInterface
{
    /**
     * @param int $recentAuthenticationLifetime Number of seconds during which an interactive
     *                                          authentication counts as recent
     */
    public function __construct(
        private int $recentAuthenticationLifetime = 900,
        private ?ClockInterface $clock = null,
    ) {
    }

    public function isAuthenticated(?TokenInterface $token = null): bool
    {
        return $token && $token->getUser();
    }

    public function isRememberMe(?TokenInterface $token = null): bool
    {
        return $token && $token instanceof RememberMeToken;
    }

    public function isFullFledged(?TokenInterface $token = null): bool
    {
        return $this->isAuthenticated($token) && !$this->isRememberMe($token);
    }

    /**
     * Time is the default signal, and deliberately the only one: an application that wants
     * to weigh other risk factors, such as the IP address changing or a recent second-factor
     * confirmation, overrides this method rather than the attribute it answers.
     *
     * An override does not have to repeat the isFullFledged() check that AuthenticatedVoter
     * already enforces; it is kept here because this method is also callable on its own.
     */
    public function isAuthenticatedRecently(?TokenInterface $token = null): bool
    {
        if (!$this->isFullFledged($token) || !$token->hasAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE)) {
            return false;
        }

        return ($this->clock?->now()->getTimestamp() ?? time()) - $token->getAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE) <= $this->recentAuthenticationLifetime;
    }
}
