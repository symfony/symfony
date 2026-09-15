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
     * @param int $recentAuthenticationLifetime     Number of seconds during which an interactive authentication counts as recent
     * @param int $veryRecentAuthenticationLifetime Number of seconds during which it counts as very recent
     */
    public function __construct(
        private int $recentAuthenticationLifetime = 2 * 3600,
        private int $veryRecentAuthenticationLifetime = 5 * 60,
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
        return $this->isAuthenticatedWithin($token, $this->recentAuthenticationLifetime);
    }

    /**
     * Same signal, shorter window by default: the bar for a step that must follow a fresh
     * proof of the credentials, such as changing the password or the e-mail address.
     * An override is where "very recently" can mean a stronger proof rather than a shorter time.
     */
    public function isAuthenticatedVeryRecently(?TokenInterface $token = null): bool
    {
        return $this->isAuthenticatedWithin($token, $this->veryRecentAuthenticationLifetime);
    }

    private function isAuthenticatedWithin(?TokenInterface $token, int $lifetime): bool
    {
        if (null === $token || !$this->isFullFledged($token) || !$token->hasAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE)) {
            return false;
        }

        return ($this->clock?->now()->getTimestamp() ?? time()) - $token->getAttribute(AuthenticatedVoter::AUTH_TIME_ATTRIBUTE) <= $lifetime;
    }
}
