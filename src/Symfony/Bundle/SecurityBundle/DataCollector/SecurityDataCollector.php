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
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\TraceableAccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\TraceableVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\FirewallMapInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class SecurityDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private bool $hasVarDumper;

    public function __construct(
        private ?TokenStorageInterface $tokenStorage = null,
        private ?RoleHierarchyInterface $roleHierarchy = null,
        private ?LogoutUrlGenerator $logoutUrlGenerator = null,
        private ?AccessDecisionManagerInterface $accessDecisionManager = null,
        private ?FirewallMapInterface $firewallMap = null,
        private ?TraceableFirewallListener $firewall = null,
    ) {
        $this->hasVarDumper = class_exists(ClassStub::class);
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
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
            $assignedRoles = $token->getRoleNames();

            $impersonatorUser = null;
            if ($token instanceof SwitchUserToken) {
                $originalToken = $token->getOriginalToken();
                $impersonatorUser = $originalToken->getUserIdentifier();
            }

            if (null !== $this->roleHierarchy) {
                foreach ($this->roleHierarchy->getReachableRoleNames($assignedRoles) as $role) {
                    if (!\in_array($role, $assignedRoles, true)) {
                        $inheritedRoles[] = $role;
                    }
                }
            }

            $logoutUrl = null;
            try {
                $logoutUrl = $this->logoutUrlGenerator?->getLogoutPath();
            } catch (\Exception) {
                // fail silently when the logout URL cannot be generated
            }

            $this->data = [
                'enabled' => true,
                'authenticated' => (bool) $token->getUser(),
                'impersonated' => null !== $impersonatorUser,
                'impersonator_user' => $impersonatorUser,
                'impersonation_exit_path' => null,
                'token' => $token,
                'token_class' => $this->hasVarDumper ? new ClassStub($token::class) : $token::class,
                'logout_url' => $logoutUrl,
                'user' => $token->getUserIdentifier(),
                'roles' => $assignedRoles,
                'inherited_roles' => array_unique($inheritedRoles),
                'supports_role_hierarchy' => null !== $this->roleHierarchy,
            ];
        }

        // collect voters and access decision manager information
        if ($this->accessDecisionManager instanceof TraceableAccessDecisionManager) {
            $this->data['voter_strategy'] = $this->accessDecisionManager->getStrategy();
            $this->data['voters'] = [];

            foreach ($this->accessDecisionManager->getVoters() as $voter) {
                if ($voter instanceof TraceableVoter) {
                    $voter = $voter->getDecoratedVoter();
                }

                $this->data['voters'][] = $this->hasVarDumper ? new ClassStub($voter::class) : $voter::class;
            }

            // collect voter details
            $decisionLog = $this->accessDecisionManager->getDecisionLog();

            foreach ($decisionLog as $key => $log) {
                $decisionLog[$key]['voter_details'] = [];
                foreach ($log['voterDetails'] as $voterDetail) {
                    $voterClass = $voterDetail['voter']::class;
                    $classData = $this->hasVarDumper ? new ClassStub($voterClass) : $voterClass;
                    $decisionLog[$key]['voter_details'][] = [
                        'class' => $classData,
                        'attributes' => $voterDetail['attributes'], // Only displayed for unanimous strategy
                        'vote' => $voterDetail['vote'],
                        'reasons' => $voterDetail['reasons'] ?? [],
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
                    'request_matcher' => $firewallConfig->getRequestMatcher(),
                    'security_enabled' => $firewallConfig->isSecurityEnabled(),
                    'stateless' => $firewallConfig->isStateless(),
                    'provider' => $firewallConfig->getProvider(),
                    'context' => $firewallConfig->getContext(),
                    'entry_point' => $firewallConfig->getEntryPoint(),
                    'access_denied_handler' => $firewallConfig->getAccessDeniedHandler(),
                    'access_denied_url' => $firewallConfig->getAccessDeniedUrl(),
                    'user_checker' => $firewallConfig->getUserChecker(),
                    'authenticators' => $firewallConfig->getAuthenticators(),
                ];

                // generate exit impersonation path from current request
                if ($this->data['impersonated'] && null !== $switchUserConfig = $firewallConfig->getSwitchUser()) {
                    $exitPath = $request->getRequestUri();
                    $exitPath .= null === $request->getQueryString() ? '?' : '&';
                    $exitPath .= \sprintf('%s=%s', urlencode($switchUserConfig['parameter']), SwitchUserListener::EXIT_VALUE);

                    $this->data['impersonation_exit_path'] = $exitPath;
                }
            }
        }

        // collect firewall listeners information
        $this->data['listeners'] = [];
        if ($this->firewall) {
            $this->data['listeners'] = $this->firewall->getWrappedListeners();
        }

        $this->data['authenticators'] = $this->firewall ? $this->firewall->getAuthenticatorsInfo() : [];

        if ($this->data['listeners'] && !($this->data['firewall']['stateless'] ?? true)) {
            $authCookieName = "{$this->data['firewall']['name']}_auth_profile_token";
            $deauthCookieName = "{$this->data['firewall']['name']}_deauth_profile_token";
            $profileToken = $response->headers->get('X-Debug-Token');

            $this->data['auth_profile_token'] = $request->cookies->get($authCookieName);
            $this->data['deauth_profile_token'] = $request->cookies->get($deauthCookieName);

            if ($this->data['authenticated'] && !$this->data['auth_profile_token']) {
                $response->headers->setCookie(new Cookie($authCookieName, $profileToken));

                $this->data['deauth_profile_token'] = null;
                $response->headers->clearCookie($deauthCookieName);
            } elseif (!$this->data['authenticated'] && !$this->data['deauth_profile_token']) {
                $response->headers->setCookie(new Cookie($deauthCookieName, $profileToken));

                $this->data['auth_profile_token'] = null;
                $response->headers->clearCookie($authCookieName);
            }
        }
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function lateCollect(): void
    {
        $this->data = $this->cloneVar($this->data);
    }

    /**
     * Checks if security is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->data['enabled'];
    }

    /**
     * Gets the user.
     */
    public function getUser(): string
    {
        return $this->data['user'];
    }

    /**
     * Gets the roles of the user.
     */
    public function getRoles(): array|Data
    {
        return $this->data['roles'];
    }

    /**
     * Gets the inherited roles of the user.
     */
    public function getInheritedRoles(): array|Data
    {
        return $this->data['inherited_roles'];
    }

    /**
     * Checks if the data contains information about inherited roles. Still the inherited
     * roles can be an empty array.
     */
    public function supportsRoleHierarchy(): bool
    {
        return $this->data['supports_role_hierarchy'];
    }

    /**
     * Checks if the user is authenticated or not.
     */
    public function isAuthenticated(): bool
    {
        return $this->data['authenticated'];
    }

    public function isImpersonated(): bool
    {
        return $this->data['impersonated'];
    }

    public function getImpersonatorUser(): ?string
    {
        return $this->data['impersonator_user'];
    }

    public function getImpersonationExitPath(): ?string
    {
        return $this->data['impersonation_exit_path'];
    }

    /**
     * Get the class name of the security token.
     */
    public function getTokenClass(): string|Data|null
    {
        return $this->data['token_class'];
    }

    /**
     * Get the full security token class as Data object.
     */
    public function getToken(): ?Data
    {
        return $this->data['token'];
    }

    /**
     * Get the logout URL.
     */
    public function getLogoutUrl(): ?string
    {
        return $this->data['logout_url'];
    }

    /**
     * Returns the FQCN of the security voters enabled in the application.
     *
     * @return string[]|Data
     */
    public function getVoters(): array|Data
    {
        return $this->data['voters'];
    }

    /**
     * Returns the strategy configured for the security voters.
     */
    public function getVoterStrategy(): string
    {
        return $this->data['voter_strategy'];
    }

    /**
     * Returns the log of the security decisions made by the access decision manager.
     */
    public function getAccessDecisionLog(): array|Data
    {
        return $this->data['access_decision_log'];
    }

    /**
     * Returns the configuration of the current firewall context.
     */
    public function getFirewall(): array|Data|null
    {
        return $this->data['firewall'];
    }

    public function getListeners(): array|Data
    {
        return $this->data['listeners'];
    }

    public function getAuthenticators(): array|Data
    {
        return $this->data['authenticators'];
    }

    public function getAuthProfileToken(): string|Data|null
    {
        return $this->data['auth_profile_token'] ?? null;
    }

    public function getDeauthProfileToken(): string|Data|null
    {
        return $this->data['deauth_profile_token'] ?? null;
    }

    public function getName(): string
    {
        return 'security';
    }
}
