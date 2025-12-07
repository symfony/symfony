<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessControl\Voter\Expression;

use Symfony\Component\AccessControl\AccessRequest;
use Symfony\Component\AccessControl\Voter\RBAC\UserWithRoleInterface;
use Symfony\Component\AccessControl\VoterInterface;
use Symfony\Component\AccessControl\VoterOutcome;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @experimental
 */
final readonly class ExpressionVoter implements VoterInterface
{
    public function __construct(
        private ExpressionLanguage $expressionLanguage,
        private AuthenticationTrustResolverInterface $trustResolver,
        private ?RoleHierarchyInterface $roleHierarchy = null,
    ) {
    }

    public function supportsAttribute(mixed $attribute): bool
    {
        return $attribute instanceof Expression;
    }

    public function supportsSubject(mixed $subject): bool
    {
        return true;
    }

    public function vote(AccessRequest $accessRequest): VoterOutcome
    {
        $variables = $this->getVariables($accessRequest);
        if ($this->expressionLanguage->evaluate($accessRequest->attribute, $variables)) {
            return VoterOutcome::grant('Access granted by expression');
        }

        return VoterOutcome::deny('Access denied by expression');
    }

    /**
     * @return array{token: TokenInterface, user: UserInterface|null, object: mixed, subject: mixed, roles: array<int, string>, role_names: array<int, string>, trust_resolver: AuthenticationTrustResolverInterface, auth_checker: AuthorizationCheckerInterface, request: Request|null}
     */
    private function getVariables(AccessRequest $accessRequest): array
    {
        $user = $accessRequest->requester?->getUser();
        $roleNames = [];
        if ($user !== null && ($user instanceof UserWithRoleInterface || method_exists($user, 'getRoles'))) {
            $roleNames = $user->getRoles();
        }

        if ($this->roleHierarchy !== null) {
            $roleNames = $this->roleHierarchy->getReachableRoleNames($roleNames);
        }

        $variables = [
            'token' => $accessRequest->requester,
            'user' => $user,
            'object' => $accessRequest->subject,
            'subject' => $accessRequest->subject,
            'role_names' => $roleNames,
            'trust_resolver' => $this->trustResolver,
            'metadata' => $accessRequest->metadata,
        ];

        if ($accessRequest->subject instanceof Request) {
            $variables['request'] = $accessRequest->subject;
        }

        return $variables;
    }
}
