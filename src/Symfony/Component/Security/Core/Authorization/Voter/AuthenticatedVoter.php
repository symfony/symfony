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
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * AuthenticatedVoter votes if an attribute like IS_AUTHENTICATED_FULLY,
 * IS_AUTHENTICATED_REMEMBERED, IS_AUTHENTICATED is present.
 *
 * This list is most restrictive to least restrictive checking.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticatedVoter implements CacheableVoterInterface
{
    public const IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    public const IS_AUTHENTICATED_REMEMBERED = 'IS_AUTHENTICATED_REMEMBERED';
    public const IS_AUTHENTICATED = 'IS_AUTHENTICATED';
    public const IS_IMPERSONATOR = 'IS_IMPERSONATOR';
    public const IS_REMEMBERED = 'IS_REMEMBERED';
    public const PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    public function __construct(
        private AuthenticationTrustResolverInterface $authenticationTrustResolver,
    ) {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        if ($attributes === [self::PUBLIC_ACCESS]) {
            return VoterInterface::ACCESS_GRANTED;
        }

        $result = VoterInterface::ACCESS_ABSTAIN;
        foreach ($attributes as $attribute) {
            if (null === $attribute || (self::IS_AUTHENTICATED_FULLY !== $attribute
                    && self::IS_AUTHENTICATED_REMEMBERED !== $attribute
                    && self::IS_AUTHENTICATED !== $attribute
                    && self::IS_IMPERSONATOR !== $attribute
                    && self::IS_REMEMBERED !== $attribute)) {
                continue;
            }

            $result = VoterInterface::ACCESS_DENIED;

            if (self::IS_AUTHENTICATED_FULLY === $attribute
                && $this->authenticationTrustResolver->isFullFledged($token)) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED_REMEMBERED === $attribute
                && ($this->authenticationTrustResolver->isRememberMe($token)
                    || $this->authenticationTrustResolver->isFullFledged($token))) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED === $attribute && $this->authenticationTrustResolver->isAuthenticated($token)) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_REMEMBERED === $attribute && $this->authenticationTrustResolver->isRememberMe($token)) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_IMPERSONATOR === $attribute && $token instanceof SwitchUserToken) {
                return VoterInterface::ACCESS_GRANTED;
            }
        }

        return $result;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, [
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
