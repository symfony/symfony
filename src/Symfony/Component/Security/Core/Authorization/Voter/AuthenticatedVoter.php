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
    /**
     * @deprecated since Symfony 5.4
     */
    public const IS_AUTHENTICATED_ANONYMOUSLY = 'IS_AUTHENTICATED_ANONYMOUSLY';
    /**
     * @deprecated since Symfony 5.4
     */
    public const IS_ANONYMOUS = 'IS_ANONYMOUS';
    public const IS_AUTHENTICATED = 'IS_AUTHENTICATED';
    public const IS_IMPERSONATOR = 'IS_IMPERSONATOR';
    public const IS_REMEMBERED = 'IS_REMEMBERED';
    public const PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    private $authenticationTrustResolver;

    public function __construct(AuthenticationTrustResolverInterface $authenticationTrustResolver)
    {
        $this->authenticationTrustResolver = $authenticationTrustResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        if ($attributes === [self::PUBLIC_ACCESS]) {
            return VoterInterface::ACCESS_GRANTED;
        }

        $result = VoterInterface::ACCESS_ABSTAIN;
        foreach ($attributes as $attribute) {
            if (null === $attribute || (self::IS_AUTHENTICATED_FULLY !== $attribute
                    && self::IS_AUTHENTICATED_REMEMBERED !== $attribute
                    && self::IS_AUTHENTICATED_ANONYMOUSLY !== $attribute
                    && self::IS_AUTHENTICATED !== $attribute
                    && self::IS_ANONYMOUS !== $attribute
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

            if (self::IS_AUTHENTICATED_ANONYMOUSLY === $attribute
                && ($this->authenticationTrustResolver->isAnonymous($token)
                    || $this->authenticationTrustResolver->isRememberMe($token)
                    || $this->authenticationTrustResolver->isFullFledged($token))) {
                trigger_deprecation('symfony/security-core', '5.4', 'The "IS_AUTHENTICATED_ANONYMOUSLY" security attribute is deprecated, use "IS_AUTHENTICATED" or "IS_AUTHENTICATED_FULLY" instead if you want to check if the request is (fully) authenticated.');

                return VoterInterface::ACCESS_GRANTED;
            }

            // @deprecated $this->authenticationTrustResolver must implement isAuthenticated() in 6.0
            if (self::IS_AUTHENTICATED === $attribute
                && (method_exists($this->authenticationTrustResolver, 'isAuthenticated')
                    ? $this->authenticationTrustResolver->isAuthenticated($token)
                    : ($token && $token->getUser()))) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_REMEMBERED === $attribute && $this->authenticationTrustResolver->isRememberMe($token)) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_ANONYMOUS === $attribute && $this->authenticationTrustResolver->isAnonymous($token)) {
                trigger_deprecation('symfony/security-core', '5.4', 'The "IS_ANONYMOUSLY" security attribute is deprecated, anonymous no longer exists in version 6.');

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
            self::IS_AUTHENTICATED_ANONYMOUSLY,
            self::IS_AUTHENTICATED,
            self::IS_ANONYMOUS,
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
