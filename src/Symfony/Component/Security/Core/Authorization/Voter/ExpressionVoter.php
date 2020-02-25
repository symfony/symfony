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
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
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
class ExpressionVoter implements VoterInterface
{
    private $expressionLanguage;
    private $trustResolver;
    private $authChecker;
    private $roleHierarchy;

    public function __construct(ExpressionLanguage $expressionLanguage, /*AuthenticationTrustResolverInterface */$trustResolver, /*AuthorizationCheckerInterface */$authChecker = null, /*RoleHierarchyInterface */$roleHierarchy = null)
    {
        // $expr, $trust, $auth, ?$role
        // $expr, $auth, ?$role
        if ($trustResolver instanceof AuthenticationTrustResolverInterface) {
            // old signature
            trigger_deprecation('symfony/security-core', '5.1', 'Passing an %s as second argument to %s is deprecated.', AuthenticationTrustResolverInterface::class, __CLASS__);
        } else {
            // new signature: ExpressionLanguage $expressionLanguage, AuthorizationCheckerInterface $authChecker, RoleHierarchyInterface $roleHierarchy = null
            $roleHierarchy = $authChecker;
            $authChecker = $trustResolver;
            $trustResolver = new AuthenticationTrustResolver(false);
        }

        if (!$authChecker instanceof AuthorizationCheckerInterface) {
            throw new \InvalidArgumentException(sprintf('Argument 2 of %s must be an instance of %s, %s given.', __METHOD__, AuthorizationCheckerInterface::class, is_object($authChecker) ? get_class($authChecker) : gettype($authChecker)));
        }

        if (null !== $roleHierarchy && !$roleHierarchy instanceof RoleHierarchyInterface) {
            throw new \InvalidArgumentException(sprintf('Argument 3 of %s must be an instance of %s or null, %s given.', __METHOD__, RoleHierarchyInterface::class, is_object($roleHierarchy) ? get_class($roleHierarchy) : gettype($roleHierarchy)));
        }

        $this->expressionLanguage = $expressionLanguage;
        $this->trustResolver = $trustResolver;
        $this->authChecker = $authChecker;
        $this->roleHierarchy = $roleHierarchy;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $subject, array $attributes)
    {
        $result = VoterInterface::ACCESS_ABSTAIN;
        $variables = null;
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof Expression) {
                continue;
            }

            if (null === $variables) {
                $variables = $this->getVariables($token, $subject);
            }

            $result = VoterInterface::ACCESS_DENIED;
            if ($this->expressionLanguage->evaluate($attribute, $variables)) {
                return VoterInterface::ACCESS_GRANTED;
            }
        }

        return $result;
    }

    private function getVariables(TokenInterface $token, $subject): array
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
