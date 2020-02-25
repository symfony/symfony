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
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverTrait;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * AuthenticatedVoter votes if an attribute like IS_AUTHENTICATED_FULLY,
 * IS_AUTHENTICATED_REMEMBERED, or IS_AUTHENTICATED_ANONYMOUSLY is present.
 *
 * This list is most restrictive to least restrictive checking.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AuthenticatedVoter implements VoterInterface
{
    const IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    const IS_AUTHENTICATED_REMEMBERED = 'IS_AUTHENTICATED_REMEMBERED';
    const IS_AUTHENTICATED_ANONYMOUSLY = 'IS_AUTHENTICATED_ANONYMOUSLY';
    const IS_ANONYMOUS = 'IS_ANONYMOUS';
    const IS_IMPERSONATOR = 'IS_IMPERSONATOR';
    const IS_REMEMBERED = 'IS_REMEMBERED';

    private $authenticationTrustResolver;

    public function __construct(AuthenticationTrustResolverInterface $authenticationTrustResolver = null)
    {
        if (null !== $authenticationTrustResolver) {
            trigger_deprecation('symfony/security-core', '5.1', 'Passing instance of "%s" as first argument to "%s" is deprecated.', AuthenticationTrustResolverInterface::class, __CLASS__);

            $this->authenticationTrustResolver = $authenticationTrustResolver;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $result = VoterInterface::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            if (null === $attribute || (self::IS_AUTHENTICATED_FULLY !== $attribute
                    && self::IS_AUTHENTICATED_REMEMBERED !== $attribute
                    && self::IS_AUTHENTICATED_ANONYMOUSLY !== $attribute
                    && self::IS_ANONYMOUS !== $attribute
                    && self::IS_IMPERSONATOR !== $attribute
                    && self::IS_REMEMBERED !== $attribute)) {
                continue;
            }

            $result = VoterInterface::ACCESS_DENIED;
            if (null === $token) {
                return $result;
            }

            if (self::IS_AUTHENTICATED_FULLY === $attribute
                && (null !== $this->authenticationTrustResolver ? $this->authenticationTrustResolver->isFullFledged($token) : $this->isFullyFledged($token))) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED_REMEMBERED === $attribute
                && (null !== $this->authenticationTrustResolver ? ($this->authenticationTrustResolver->isRememberMe($token)
                    || $this->authenticationTrustResolver->isFullFledged($token)) : ($token instanceof RememberMeToken || $this->isFullyFledged($token)))) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_AUTHENTICATED_ANONYMOUSLY === $attribute
                && (null !== $this->authenticationTrustResolver ? ($this->authenticationTrustResolver->isAnonymous($token)
                    || $this->authenticationTrustResolver->isRememberMe($token)
                    || $this->authenticationTrustResolver->isFullFledged($token)) : ($token instanceof AnonymousToken || $token instanceof RememberMeToken || $this->isFullyFledged($token)))) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_REMEMBERED === $attribute && (null !== $this->authenticationTrustResolver ? $this->authenticationTrustResolver->isRememberMe($token) : $token instanceof RememberMeToken)) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_ANONYMOUS === $attribute && (null !== $this->authenticationTrustResolver ? $this->authenticationTrustResolver->isAnonymous($token) : $token instanceof AnonymousToken)) {
                return VoterInterface::ACCESS_GRANTED;
            }

            if (self::IS_IMPERSONATOR === $attribute && $token instanceof SwitchUserToken) {
                return VoterInterface::ACCESS_GRANTED;
            }
        }

        return $result;
    }

    private function isFullyFledged(TokenInterface $token): bool
    {
        return !$token instanceof AnonymousToken && !$token instanceof RememberMeToken;
    }
}
