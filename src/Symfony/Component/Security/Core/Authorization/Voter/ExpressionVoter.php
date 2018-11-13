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
            $roleHierarchy = $authChecker;
            $authChecker = null;
        } elseif (null !== $authChecker && !$authChecker instanceof AuthorizationCheckerInterface) {
            throw new \InvalidArgumentException(sprintf('Argument 3 passed to %s() must be an instance of Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface or null, %s given.', __METHOD__, \is_object($authChecker) ? \get_class($authChecker) : \gettype($authChecker)));
        }

        $this->expressionLanguage = $expressionLanguage;
        $this->trustResolver = $trustResolver;
        $this->roleHierarchy = $roleHierarchy;
        $this->authChecker = $authChecker;
    }

    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
    {
        $this->expressionLanguage->registerProvider($provider);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsAttribute($attribute)
    {
        return $attribute instanceof Expression;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass($class)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function vote(TokenInterface $token, $object, array $attributes)
    {
        $result = VoterInterface::ACCESS_ABSTAIN;
        $variables = null;
        foreach ($attributes as $attribute) {
            if (!$this->supportsAttribute($attribute)) {
                continue;
            }

            if (null === $variables) {
                $variables = $this->getVariables($token, $object);
            }

            $result = VoterInterface::ACCESS_DENIED;
            if ($this->expressionLanguage->evaluate($attribute, $variables)) {
                return VoterInterface::ACCESS_GRANTED;
            }
        }

        return $result;
    }

    private function getVariables(TokenInterface $token, $object)
    {
        if (null !== $this->roleHierarchy) {
            $roles = $this->roleHierarchy->getReachableRoles($token->getRoles());
        } else {
            $roles = $token->getRoles();
        }

        $variables = array(
            'token' => $token,
            'user' => $token->getUser(),
            'object' => $object,
            'subject' => $object,
            'roles' => array_map(function ($role) { return $role->getRole(); }, $roles),
            'trust_resolver' => $this->trustResolver,
        );

        // this is mainly to propose a better experience when the expression is used
        // in an access control rule, as the developer does not know that it's going
        // to be handled by this voter
        if ($object instanceof Request) {
            $variables['request'] = $object;
        }

        if (null !== $this->authChecker) {
            $variables['auth_checker'] = $this->authChecker;
        }

        return $variables;
    }
}
