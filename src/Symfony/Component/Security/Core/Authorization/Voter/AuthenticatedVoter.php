<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\OfflineTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * AuthenticatedVoter votes if an attribute like IS_AUTHENTICATED_RECENTLY,
 * IS_AUTHENTICATED_FULLY, IS_AUTHENTICATED_REMEMBERED, IS_AUTHENTICATED is present.
 *
 * This list is most restrictive to least restrictive checking.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticatedVoter implements CacheableVoterInterface
{
    public const IS_AUTHENTICATED_RECENTLY = 'IS_AUTHENTICATED_RECENTLY';
    public const IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    public const IS_AUTHENTICATED_REMEMBERED = 'IS_AUTHENTICATED_REMEMBERED';
    public const IS_AUTHENTICATED = 'IS_AUTHENTICATED';
    public const IS_IMPERSONATOR = 'IS_IMPERSONATOR';
    public const IS_REMEMBERED = 'IS_REMEMBERED';
    public const PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    /**
     * Token attribute holding the Unix timestamp of the last interactive authentication.
     */
    public const AUTH_TIME_ATTRIBUTE = 'auth_time';

    /**
     * @param int $recentAuthenticationLifetime Number of seconds during which an interactive
     *                                          authentication grants IS_AUTHENTICATED_RECENTLY
     */
    public function __construct(
        private AuthenticationTrustResolverInterface $authenticationTrustResolver,
        private int $recentAuthenticationLifetime = 900,
        private ?ClockInterface $clock = null,
    ) {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        if ($attributes === [self::PUBLIC_ACCESS]) {
            $vote?->addReason('Access is public.');

            return VoterInterface::ACCESS_GRANTED;
        }

        $result = VoterInterface::ACCESS_ABSTAIN;
        $deniedReasons = [];
        foreach ($attributes as $attribute) {
            if (null === $attribute || !\in_array($attribute, [
                self::IS_AUTHENTICATED_RECENTLY,
                self::IS_AUTHENTICATED_FULLY,
                self::IS_AUTHENTICATED_REMEMBERED,
                self::IS_AUTHENTICATED,
                self::IS_IMPERSONATOR,
                self::IS_REMEMBERED,
            ], true)) {
                continue;
            }

            if ($token instanceof OfflineTokenInterface) {
                throw new InvalidArgumentException('Cannot decide on authentication attributes when an offline token is used.');
            }

            $result = VoterInterface::ACCESS_DENIED;

            switch ($attribute) {
                case self::IS_AUTHENTICATED_RECENTLY:
                    if (!$this->authenticationTrustResolver->isFullFledged($token)) {
                        $deniedReasons[] = 'The user is not fully authenticated.';
                        break;
                    }

                    if ($token->hasAttribute(self::AUTH_TIME_ATTRIBUTE)
                        && ($this->clock?->now()->getTimestamp() ?? time()) - $token->getAttribute(self::AUTH_TIME_ATTRIBUTE) <= $this->recentAuthenticationLifetime
                    ) {
                        $vote?->addReason('The user authenticated recently.');

                        return VoterInterface::ACCESS_GRANTED;
                    }

                    // the user is authenticated, so the denial is about how long ago
                    // that happened and not about who they are
                    $deniedReasons[] = 'The user is not authenticated recently enough.';
                    break;

                case self::IS_AUTHENTICATED_FULLY:
                    if ($this->authenticationTrustResolver->isFullFledged($token)) {
                        $vote?->addReason('The user is fully authenticated.');

                        return VoterInterface::ACCESS_GRANTED;
                    }

                    $deniedReasons[] = 'The user is not fully authenticated.';
                    break;

                case self::IS_AUTHENTICATED_REMEMBERED:
                    if ($this->authenticationTrustResolver->isFullFledged($token)) {
                        $vote?->addReason('The user is fully authenticated.');

                        return VoterInterface::ACCESS_GRANTED;
                    }

                    if ($this->authenticationTrustResolver->isRememberMe($token)) {
                        $vote?->addReason('The user is remembered.');

                        return VoterInterface::ACCESS_GRANTED;
                    }

                    $deniedReasons[] = 'The user is neither fully authenticated nor remembered.';
                    break;

                case self::IS_AUTHENTICATED:
                    if ($this->authenticationTrustResolver->isAuthenticated($token)) {
                        $vote?->addReason('The user is authenticated.');

                        return VoterInterface::ACCESS_GRANTED;
                    }

                    $deniedReasons[] = 'The user is not authenticated.';
                    break;

                case self::IS_REMEMBERED:
                    if ($this->authenticationTrustResolver->isRememberMe($token)) {
                        $vote?->addReason('The user is remembered.');

                        return VoterInterface::ACCESS_GRANTED;
                    }

                    $deniedReasons[] = 'The user is not remembered.';
                    break;

                case self::IS_IMPERSONATOR:
                    if ($token instanceof SwitchUserToken) {
                        $vote?->addReason('The user is impersonating another user.');

                        return VoterInterface::ACCESS_GRANTED;
                    }

                    $deniedReasons[] = 'The user is not impersonating another user.';
                    break;
            }
        }

        if (VoterInterface::ACCESS_DENIED === $result) {
            foreach (array_unique($deniedReasons) as $deniedReason) {
                $vote?->addReason($deniedReason);
            }
        }

        return $result;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, [
            self::IS_AUTHENTICATED_RECENTLY,
            self::IS_AUTHENTICATED_FULLY,
            self::IS_AUTHENTICATED_REMEMBERED,
            self::IS_AUTHENTICATED,
            self::IS_IMPERSONATOR,
            self::IS_REMEMBERED,
            self::PUBLIC_ACCESS,
        ], true);
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }
}
