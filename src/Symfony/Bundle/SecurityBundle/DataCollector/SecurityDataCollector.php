<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DataCollector;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Security\Core\Role\RoleInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private $tokenStorage;
    private $roleHierarchy;
    private $logoutUrlGenerator;
    private $accessDecisionManager;
    private $firewallMap;
    private $hasVarDumper;

    /**
     * @param TokenStorageInterface|null          $tokenStorage
     * @param RoleHierarchyInterface|null         $roleHierarchy
     * @param LogoutUrlGenerator|null             $logoutUrlGenerator
     * @param AccessDecisionManagerInterface|null $accessDecisionManager
     * @param FirewallMapInterface|null           $firewallMap
     */
    public function __construct(TokenStorageInterface $tokenStorage = null, RoleHierarchyInterface $roleHierarchy = null, LogoutUrlGenerator $logoutUrlGenerator = null, AccessDecisionManagerInterface $accessDecisionManager = null, FirewallMapInterface $firewallMap = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->roleHierarchy = $roleHierarchy;
        $this->logoutUrlGenerator = $logoutUrlGenerator;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->firewallMap = $firewallMap;
        $this->hasVarDumper = class_exists(ClassStub::class);
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (null === $this->tokenStorage) {
            $this->data = array(
                'enabled' => false,
                'authenticated' => false,
                'token' => null,
                'token_class' => null,
                'logout_url' => null,
                'user' => '',
                'roles' => array(),
                'inherited_roles' => array(),
                'supports_role_hierarchy' => null !== $this->roleHierarchy,
            );
        } elseif (null === $token = $this->tokenStorage->getToken()) {
            $this->data = array(
                'enabled' => true,
                'authenticated' => false,
                'token' => null,
                'token_class' => null,
                'logout_url' => null,
                'user' => '',
                'roles' => array(),
                'inherited_roles' => array(),
                'supports_role_hierarchy' => null !== $this->roleHierarchy,
            );
        } else {
            $inheritedRoles = array();
            $assignedRoles = $token->getRoles();

            if (null !== $this->roleHierarchy) {
                $allRoles = $this->roleHierarchy->getReachableRoles($assignedRoles);
                foreach ($allRoles as $role) {
                    if (!in_array($role, $assignedRoles, true)) {
                        $inheritedRoles[] = $role;
                    }
                }
            }

            $logoutUrl = null;
            try {
                if (null !== $this->logoutUrlGenerator) {
                    $logoutUrl = $this->logoutUrlGenerator->getLogoutPath();
                }
            } catch (\Exception $e) {
                // fail silently when the logout URL cannot be generated
            }

            $this->data = array(
                'enabled' => true,
                'authenticated' => $token->isAuthenticated(),
                'token' => $token,
                'token_class' => $this->hasVarDumper ? new ClassStub(get_class($token)) : get_class($token),
                'logout_url' => $logoutUrl,
                'user' => $token->getUsername(),
                'roles' => array_map(function (RoleInterface $role) { return $role->getRole(); }, $assignedRoles),
                'inherited_roles' => array_unique(array_map(function (RoleInterface $role) { return $role->getRole(); }, $inheritedRoles)),
                'supports_role_hierarchy' => null !== $this->roleHierarchy,
            );
        }

        // collect voters and access decision manager information
        if ($this->accessDecisionManager instanceof TraceableAccessDecisionManager) {
            $this->data['access_decision_log'] = $this->accessDecisionManager->getDecisionLog();
            $this->data['voter_strategy'] = $this->accessDecisionManager->getStrategy();

            foreach ($this->accessDecisionManager->getVoters() as $voter) {
                $this->data['voters'][] = $this->hasVarDumper ? new ClassStub(get_class($voter)) : get_class($voter);
            }
        } else {
            $this->data['access_decision_log'] = array();
            $this->data['voter_strategy'] = 'unknown';
            $this->data['voters'] = array();
        }

        // collect firewall context information
        $this->data['firewall'] = null;
        if ($this->firewallMap instanceof FirewallMap) {
            $firewallConfig = $this->firewallMap->getFirewallConfig($request);
            if (null !== $firewallConfig) {
                $this->data['firewall'] = array(
                    'name' => $firewallConfig->getName(),
                    'allows_anonymous' => $firewallConfig->allowsAnonymous(),
                    'request_matcher' => $firewallConfig->getRequestMatcher(),
                    'security_enabled' => $firewallConfig->isSecurityEnabled(),
                    'stateless' => $firewallConfig->isStateless(),
                    'provider' => $firewallConfig->getProvider(),
                    'context' => $firewallConfig->getContext(),
                    'entry_point' => $firewallConfig->getEntryPoint(),
                    'access_denied_handler' => $firewallConfig->getAccessDeniedHandler(),
                    'access_denied_url' => $firewallConfig->getAccessDeniedUrl(),
                    'user_checker' => $firewallConfig->getUserChecker(),
                    'listeners' => $firewallConfig->getListeners(),
                );
            }
        }
    }

    public function lateCollect()
    {
        $this->data = $this->cloneVar($this->data);
    }

    /**
     * Checks if security is enabled.
     *
     * @return bool true if security is enabled, false otherwise
     */
    public function isEnabled()
    {
        return $this->data['enabled'];
    }

    /**
     * Gets the user.
     *
     * @return string The user
     */
    public function getUser()
    {
        return $this->data['user'];
    }

    /**
     * Gets the roles of the user.
     *
     * @return array The roles
     */
    public function getRoles()
    {
        return $this->data['roles'];
    }

    /**
     * Gets the inherited roles of the user.
     *
     * @return array The inherited roles
     */
    public function getInheritedRoles()
    {
        return $this->data['inherited_roles'];
    }

    /**
     * Checks if the data contains information about inherited roles. Still the inherited
     * roles can be an empty array.
     *
     * @return bool true if the profile was contains inherited role information
     */
    public function supportsRoleHierarchy()
    {
        return $this->data['supports_role_hierarchy'];
    }

    /**
     * Checks if the user is authenticated or not.
     *
     * @return bool true if the user is authenticated, false otherwise
     */
    public function isAuthenticated()
    {
        return $this->data['authenticated'];
    }

    /**
     * Get the class name of the security token.
     *
     * @return string The token
     */
    public function getTokenClass()
    {
        return $this->data['token_class'];
    }

    /**
     * Get the full security token class as Data object.
     *
     * @return Data
     */
    public function getToken()
    {
        return $this->data['token'];
    }

    /**
     * Get the provider key (i.e. the name of the active firewall).
     *
     * @return string The provider key
     */
    public function getLogoutUrl()
    {
        return $this->data['logout_url'];
    }

    /**
     * Returns the FQCN of the security voters enabled in the application.
     *
     * @return string[]
     */
    public function getVoters()
    {
        return $this->data['voters'];
    }

    /**
     * Returns the strategy configured for the security voters.
     *
     * @return string
     */
    public function getVoterStrategy()
    {
        return $this->data['voter_strategy'];
    }

    /**
     * Returns the log of the security decisions made by the access decision manager.
     *
     * @return array
     */
    public function getAccessDecisionLog()
    {
        return $this->data['access_decision_log'];
    }

    /**
     * Returns the configuration of the current firewall context.
     *
     * @return array
     */
    public function getFirewall()
    {
        return $this->data['firewall'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'security';
    }
}
