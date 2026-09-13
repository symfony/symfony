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
     * Most restrictive first: only the reason of the strictest attribute that failed is reported.
     */
    private const DENIAL_REASONS = [
        self::IS_AUTHENTICATED_RECENTLY => 'The user is not authenticated recently enough.',
        self::IS_AUTHENTICATED_FULLY => 'The user is not fully authenticated.',
        self::IS_AUTHENTICATED_REMEMBERED => 'The user is neither fully authenticated nor remembered.',
        self::IS_AUTHENTICATED => 'The user is not authenticated.',
        self::IS_IMPERSONATOR => 'The user is not impersonating another user.',
        self::IS_REMEMBERED => 'The user is not remembered.',
    ];

    public function __construct(
        private AuthenticationTrustResolverInterface $authenticationTrustResolver,
    ) {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        if ($attributes === [self::PUBLIC_ACCESS]) {
            $vote?->addReason('Access is public.');

            return VoterInterface::ACCESS_GRANTED;
        }

        $result = VoterInterface::ACCESS_ABSTAIN;
        foreach ($attributes as $attribute) {
            if (null === $attribute || (self::IS_AUTHENTICATED_RECENTLY !== $attribute
                    && self::IS_AUTHENTICATED_FULLY !== $attribute
                    && self::IS_AUTHENTICATED_REMEMBERED !== $attribute
                    && self::IS_AUTHENTICATED !== $attribute
                    && self::IS_IMPERSONATOR !== $attribute
                    && self::IS_REMEMBERED !== $attribute)) {
                continue;
            }

            if ($token instanceof OfflineTokenInterface) {
                throw new InvalidArgumentException('Cannot decide on authentication attributes when an offline token is used.');
            }

            $result = VoterInterface::ACCESS_DENIED;

            // being full fledged is an invariant of the attribute, not part of the strategy:
            // a remember-me cookie is precisely not proof that the user still holds the
            // credentials, so no custom trust resolver gets to grant on one
            if (self::IS_AUTHENTICATED_RECENTLY === $attribute
                && $this->authenticationTrustResolver->isFullFledged($token)
                && $this->isAuthenticatedRecently($token)
            ) {
                $vote?->addReason('The user authenticated recently.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if ((self::IS_AUTHENTICATED_FULLY === $attribute || self::IS_AUTHENTICATED_REMEMBERED === $attribute)
                && $this->authenticationTrustResolver->isFullFledged($token)
            ) {
                $vote?->addReason('The user is fully authenticated.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED_REMEMBERED === $attribute
                && $this->authenticationTrustResolver->isRememberMe($token)
            ) {
                $vote?->addReason('The user is remembered.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED === $attribute && $this->authenticationTrustResolver->isAuthenticated($token)) {
                $vote?->addReason('The user is authenticated.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_REMEMBERED === $attribute && $this->authenticationTrustResolver->isRememberMe($token)) {
                $vote?->addReason('The user is remembered.');

                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_IMPERSONATOR === $attribute && $token instanceof SwitchUserToken) {
                $vote?->addReason('The user is impersonating another user.');

                return VoterInterface::ACCESS_GRANTED;
            }
        }

        if (VoterInterface::ACCESS_DENIED === $result) {
            foreach (self::DENIAL_REASONS as $deniedAttribute => $reason) {
                if (\in_array($deniedAttribute, $attributes, true)) {
                    $vote?->addReason($reason);

                    break;
                }
            }
        }

        return $result;
    }

    private function isAuthenticatedRecently(TokenInterface $token): bool
    {
        if (!method_exists($this->authenticationTrustResolver, 'isAuthenticatedRecently')) {
            trigger_deprecation('symfony/security-core', '8.2', 'Not implementing "%s::isAuthenticatedRecently()" is deprecated, the method will be added to the interface in 9.0; "IS_AUTHENTICATED_RECENTLY" is denied until then.', \get_class($this->authenticationTrustResolver));

            return false;
        }

        return $this->authenticationTrustResolver->isAuthenticatedRecently($token);
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
