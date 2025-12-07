<?php

namespace Symfony\Component\AccessControl\Voter\ABAC;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\VoterInterface;
use Symfony\Component\AccessControl\VoterOutcome;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\OfflineTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * @experimental
 */
final readonly class AuthenticatedVoter implements VoterInterface
{
    public function __construct(
        private AuthenticationTrustResolverInterface $authenticationTrustResolver,
    ){}

    public function vote(AccessRequest $accessRequest): VoterOutcome
    {
        $attribute = AuthenticationState::fromValue($accessRequest->attribute);
        if (!$accessRequest->requester instanceof TokenInterface) {
            return VoterOutcome::deny('The token is not an instance of TokenInterface.');
        }

        if ($attribute === null) {
            return VoterOutcome::abstain('The attribute is not an authentication state.');
        }
        if ($attribute === AuthenticationState::PUBLIC_ACCESS) {
            return VoterOutcome::grant('Access granted to public access');
        }

        if ($accessRequest->requester instanceof OfflineTokenInterface) {
            throw new InvalidArgumentException('Cannot decide on authentication attributes when an offline token is used.');
        }

        if (AuthenticationState::IS_AUTHENTICATED_FULLY === $attribute
            && $this->authenticationTrustResolver->isFullFledged($accessRequest->requester)) {
            return VoterOutcome::grant('Access granted by fully authenticated user.');
        }

        if (AuthenticationState::IS_AUTHENTICATED_REMEMBERED === $attribute
            && ($this->authenticationTrustResolver->isRememberMe($accessRequest->requester)
                || $this->authenticationTrustResolver->isFullFledged($accessRequest->requester))) {
            return VoterOutcome::grant('Access granted by remembered user.');
        }

        if (AuthenticationState::IS_AUTHENTICATED === $attribute && $this->authenticationTrustResolver->isAuthenticated($accessRequest->requester)) {
            return VoterOutcome::grant('Access granted by authenticated user.');
        }

        if (AuthenticationState::IS_REMEMBERED === $attribute && $this->authenticationTrustResolver->isRememberMe($accessRequest->requester)) {
            return VoterOutcome::grant('Access granted by remembered user.');
        }

        if (AuthenticationState::IS_IMPERSONATOR === $attribute && $accessRequest->requester instanceof SwitchUserToken) {
            return VoterOutcome::grant('Access granted by impersonator.');
        }

        return VoterOutcome::deny('The user does not have the required authentication state.');
    }

    public function supportsAttribute(mixed $attribute): bool
    {
        return \is_string($attribute) && \in_array($attribute, AuthenticationState::caseNames(), true);
    }

    public function supportsSubject(mixed $subject): bool
    {
        return true;
    }
}

