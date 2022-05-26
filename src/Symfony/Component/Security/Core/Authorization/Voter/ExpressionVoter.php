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

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\ExpressionLanguage;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * ExpressionVoter votes based on the evaluation of an expression.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionVoter implements CacheableVoterInterface
{
    private ExpressionLanguage $expressionLanguage;
    private AuthenticationTrustResolverInterface $trustResolver;
    private AuthorizationCheckerInterface $authChecker;
    private ?RoleHierarchyInterface $roleHierarchy;

    public function __construct(ExpressionLanguage $expressionLanguage, AuthenticationTrustResolverInterface $trustResolver, AuthorizationCheckerInterface $authChecker, RoleHierarchyInterface $roleHierarchy = null)
    {
        $this->expressionLanguage = $expressionLanguage;
        $this->trustResolver = $trustResolver;
        $this->authChecker = $authChecker;
        $this->roleHierarchy = $roleHierarchy;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return false;
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }

    public function getVote(TokenInterface $token, mixed $subject, array $attributes): Vote
    {
        $result = Vote::createAbstain();
        $variables = null;
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof Expression) {
                continue;
            }

            $variables ??= $this->getVariables($token, $subject);

            $result = Vote::createDenied();
            if ($this->expressionLanguage->evaluate($attribute, $variables)) {
                return Vote::createGranted();
            }
        }

        return $result;
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes): int
    {
        trigger_deprecation('symfony/security-core', '6.2', 'Method "%s::vote()" has been deprecated, use "%s::getVote()" instead.', __CLASS__, __CLASS__);

        return $this->getVote($token, $subject, $attributes)->getAccess();
    }

    private function getVariables(TokenInterface $token, mixed $subject): array
    {
        $roleNames = $token->getRoleNames();

        if (null !== $this->roleHierarchy) {
            $roleNames = $this->roleHierarchy->getReachableRoleNames($roleNames);
        }

        $variables = [
            'token' => $token,
            'user' => $token->getUser(),
            'object' => $subject,
            'subject' => $subject,
            'role_names' => $roleNames,
            'trust_resolver' => $this->trustResolver,
            'auth_checker' => $this->authChecker,
        ];

        // this is mainly to propose a better experience when the expression is used
        // in an access control rule, as the developer does not know that it's going
        // to be handled by this voter
        if ($subject instanceof Request) {
            $variables['request'] = $subject;
        }

        return $variables;
    }
}
