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

use Symfony\Bundle\SecurityBundle\Debug\TraceableFirewallListener;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;

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
    private $firewall;
    private $hasVarDumper;

    public function __construct(TokenStorageInterface $tokenStorage = null, RoleHierarchyInterface $roleHierarchy = null, LogoutUrlGenerator $logoutUrlGenerator = null, AccessDecisionManagerInterface $accessDecisionManager = null, FirewallMapInterface $firewallMap = null, TraceableFirewallListener $firewall = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->roleHierarchy = $roleHierarchy;
        $this->logoutUrlGenerator = $logoutUrlGenerator;
        $this->accessDecisionManager = $accessDecisionManager;
        $this->firewallMap = $firewallMap;
        $this->firewall = $firewall;
        $this->hasVarDumper = class_exists(ClassStub::class);
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (null === $this->tokenStorage) {
            $this->data = [
                'enabled' => false,
                'authenticated' => false,
                'impersonated' => false,
                'impersonator_user' => null,
                'impersonation_exit_path' => null,
                'token' => null,
                'token_class' => null,
                'logout_url' => null,
                'user' => '',
                'roles' => [],
                'inherited_roles' => [],
                'supports_role_hierarchy' => null !== $this->roleHierarchy,
            ];
        } elseif (null === $token = $this->tokenStorage->getToken()) {
            $this->data = [
                'enabled' => true,
                'authenticated' => false,
                'impersonated' => false,
                'impersonator_user' => null,
                'impersonation_exit_path' => null,
                'token' => null,
                'token_class' => null,
                'logout_url' => null,
                'user' => '',
                'roles' => [],
                'inherited_roles' => [],
                'supports_role_hierarchy' => null !== $this->roleHierarchy,
            ];
        } else {
            $inheritedRoles = [];
            $assignedRoles = $token->getRoles();

            $impersonatorUser = null;
            foreach ($assignedRoles as $role) {
                if ($role instanceof SwitchUserRole) {
                    $impersonatorUser = $role->getSource()->getUsername();
                    break;
                }
            }

            if (null !== $this->roleHierarchy) {
                $allRoles = $this->roleHierarchy->getReachableRoles($assignedRoles);
                foreach ($allRoles as $role) {
                    if (!\in_array($role, $assignedRoles, true)) {
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

            $this->data = [
                'enabled' => true,
                'authenticated' => $token->isAuthenticated(),
                'impersonated' => null !== $impersonatorUser,
                'impersonator_user' => $impersonatorUser,
                'impersonation_exit_path' => null,
                'token' => $token,
                'token_class' => $this->hasVarDumper ? new ClassStub(\get_class($token)) : \get_class($token),
                'logout_url' => $logoutUrl,
                'user' => $token->getUsername(),
                'roles' => array_map(function (Role $role) { return $role->getRole(); }, $assignedRoles),
                'inherited_roles' => array_unique(array_map(function (Role $role) { return $role->getRole(); }, $inheritedRoles)),
                'supports_role_hierarchy' => null !== $this->roleHierarchy,
            ];
        }

        // collect voters and access decision manager information
        if ($this->accessDecisionManager instanceof TraceableAccessDecisionManager) {
            $this->data['voter_strategy'] = $this->accessDecisionManager->getStrategy();

            foreach ($this->accessDecisionManager->getVoters() as $voter) {
                if ($voter instanceof TraceableVoter) {
                    $voter = $voter->getDecoratedVoter();
                }

                $this->data['voters'][] = $this->hasVarDumper ? new ClassStub(\get_class($voter)) : \get_class($voter);
            }

            // collect voter details
            $decisionLog = $this->accessDecisionManager->getDecisionLog();
            foreach ($decisionLog as $key => $log) {
                $decisionLog[$key]['voter_details'] = [];
                foreach ($log['voterDetails'] as $voterDetail) {
                    $voterClass = \get_class($voterDetail['voter']);
                    $classData = $this->hasVarDumper ? new ClassStub($voterClass) : $voterClass;
                    $decisionLog[$key]['voter_details'][] = [
                        'class' => $classData,
                        'attributes' => $voterDetail['attributes'], // Only displayed for unanimous strategy
                        'vote' => $voterDetail['vote'],
                    ];
                }
                unset($decisionLog[$key]['voterDetails']);
            }

            $this->data['access_decision_log'] = $decisionLog;
        } else {
            $this->data['access_decision_log'] = [];
            $this->data['voter_strategy'] = 'unknown';
            $this->data['voters'] = [];
        }

        // collect firewall context information
        $this->data['firewall'] = null;
        if ($this->firewallMap instanceof FirewallMap) {
            $firewallConfig = $this->firewallMap->getFirewallConfig($request);
            if (null !== $firewallConfig) {
                $this->data['firewall'] = [
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
                ];

                // generate exit impersonation path from current request
                if ($this->data['impersonated'] && null !== $switchUserConfig = $firewallConfig->getSwitchUser()) {
                    $exitPath = $request->getRequestUri();
                    $exitPath .= null === $request->getQueryString() ? '?' : '&';
                    $exitPath .= sprintf('%s=%s', urlencode($switchUserConfig['parameter']), SwitchUserListener::EXIT_VALUE);

                    $this->data['impersonation_exit_path'] = $exitPath;
                }
            }
        }

        // collect firewall listeners information
        $this->data['listeners'] = [];
        if ($this->firewall) {
            $this->data['listeners'] = $this->firewall->getWrappedListeners();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->data = [];
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

    public function isImpersonated()
    {
        return $this->data['impersonated'];
    }

    public function getImpersonatorUser()
    {
        return $this->data['impersonator_user'];
    }

    public function getImpersonationExitPath()
    {
        return $this->data['impersonation_exit_path'];
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
     * Get the logout URL.
     *
     * @return string The logout URL
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

    public function getListeners()
    {
        return $this->data['listeners'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'security';
    }
}
