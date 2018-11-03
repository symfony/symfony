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
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
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
class ExpressionVoter implements VoterInterface
{
    private $expressionLanguage;
    private $trustResolver;
    private $authChecker;
    private $roleHierarchy;

    /**
     * @param AuthorizationCheckerInterface $authChecker
     */
    public function __construct(ExpressionLanguage $expressionLanguage, AuthenticationTrustResolverInterface $trustResolver, $authChecker = null, RoleHierarchyInterface $roleHierarchy = null)
    {
        if ($authChecker instanceof RoleHierarchyInterface) {
            @trigger_error(sprintf('Passing a RoleHierarchyInterface to "%s()" is deprecated since Symfony 4.2. Pass an AuthorizationCheckerInterface instead.', __METHOD__), E_USER_DEPRECATED);
            $roleHierarchy = $authChecker;
            $authChecker = null;
        } elseif (null === $authChecker) {
            @trigger_error(sprintf('Argument 3 passed to "%s()" should be an instance of AuthorizationCheckerInterface, not passing it is deprecated since Symfony 4.2.', __METHOD__), E_USER_DEPRECATED);
        } elseif (!$authChecker instanceof AuthorizationCheckerInterface) {
            throw new \InvalidArgumentException(sprintf('Argument 3 passed to %s() must be an instance of %s or null, %s given.', __METHOD__, AuthorizationCheckerInterface::class, \is_object($authChecker) ? \get_class($authChecker) : \gettype($authChecker)));
        }

        $this->expressionLanguage = $expressionLanguage;
        $this->trustResolver = $trustResolver;
        $this->authChecker = $authChecker;
        $this->roleHierarchy = $roleHierarchy;
    }

    /**
     * @deprecated since Symfony 4.1, register the provider directly on the injected ExpressionLanguage instance instead.
     */
    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
    {
        @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.1, register the provider directly on the injected ExpressionLanguage instance instead.', __METHOD__), E_USER_DEPRECATED);

        $this->expressionLanguage->registerProvider($provider);
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

    private function getVariables(TokenInterface $token, $subject)
    {
        if (null !== $this->roleHierarchy) {
            $roles = $this->roleHierarchy->getReachableRoles($token->getRoles());
        } else {
            $roles = $token->getRoles();
        }

        $variables = array(
            'token' => $token,
            'user' => $token->getUser(),
            'object' => $subject,
            'subject' => $subject,
            'roles' => array_map(function ($role) { return $role->getRole(); }, $roles),
            'trust_resolver' => $this->trustResolver,
            'auth_checker' => $this->authChecker,
        );

        // this is mainly to propose a better experience when the expression is used
        // in an access control rule, as the developer does not know that it's going
        // to be handled by this voter
        if ($subject instanceof Request) {
            $variables['request'] = $subject;
        }

        return $variables;
    }
}
