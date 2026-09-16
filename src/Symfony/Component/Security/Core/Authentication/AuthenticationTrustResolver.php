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
     * The default answers on time alone, counted from whichever method was proven last.
     * An application that wants a stricter policy, such as requiring a second factor or a
     * password entered minutes ago, overrides this method rather than the attribute it
     * answers, and reads the same map to do so.
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
        if (null === $token || !$this->isFullFledged($token)) {
            return false;
        }

        if (!method_exists($token, 'getAuthenticationProofs')) {
            trigger_deprecation('symfony/security-core', '8.2', 'Not implementing "%s::getAuthenticationProofs()" is deprecated, the method will be added to "%s" in 9.0; no authentication proof is read until then.', get_debug_type($token), TokenInterface::class);

            return false;
        }

        if (!$proofs = $token->getAuthenticationProofs()) {
            return false;
        }

        return ($this->clock?->now()->getTimestamp() ?? time()) - max($proofs) <= $lifetime;
    }
}
