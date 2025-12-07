<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessControl\Voter\RBAC;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\VoterInterface;
use Symfony\Component\AccessControl\VoterOutcome;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @experimental
 */
readonly class RoleVoter implements VoterInterface
{
    public function __construct(
        private string $prefix = 'ROLE_',
    ){}

    public function vote(AccessRequest $accessRequest): VoterOutcome
    {
        $roles = $this->extractRoles($accessRequest->requester);

        if (!\is_string($accessRequest->attribute) || !str_starts_with($accessRequest->attribute, $this->prefix)) {
            return VoterOutcome::abstain('The attribute is not a role.');
        }

        if (\in_array($accessRequest->attribute, $roles, true)) {
            return VoterOutcome::grant('The user has the required role.');
        }

        return VoterOutcome::deny('The user does not have the required role.');
    }

    public function supportsAttribute(mixed $attribute): bool
    {
        return \is_string($attribute) && str_starts_with($attribute, $this->prefix);
    }

    public function supportsSubject(mixed $subject): bool
    {
        return true;
    }

    protected function extractRoles(?TokenInterface $requester): array
    {
        $user = $requester?->getUser();
        if (!$user instanceof UserInterface && !$user instanceof UserWithRoleInterface) {
            return [];
        }

        if ($user instanceof UserWithRoleInterface || method_exists($user, 'getRoles')) {
            return $user->getRoles();
        }

        return [];
    }
}
