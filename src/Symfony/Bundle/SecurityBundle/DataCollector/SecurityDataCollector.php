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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Role\RoleInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityDataCollector extends DataCollector
{
    private $tokenStorage;
    private $roleHierarchy;
    private $logoutUrlGenerator;

    public function __construct(TokenStorageInterface $tokenStorage = null, RoleHierarchyInterface $roleHierarchy = null, LogoutUrlGenerator $logoutUrlGenerator = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->roleHierarchy = $roleHierarchy;
        $this->logoutUrlGenerator = $logoutUrlGenerator;
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

            $this->data = array(
                'enabled' => true,
                'authenticated' => $token->isAuthenticated(),
                'token_class' => \get_class($token),
                'logout_url' => $logoutUrl,
                'user' => $token->getUsername(),
                'roles' => array_map(function (RoleInterface $role) { return $role->getRole(); }, $assignedRoles),
                'inherited_roles' => array_unique(array_map(function (RoleInterface $role) { return $role->getRole(); }, $inheritedRoles)),
                'supports_role_hierarchy' => null !== $this->roleHierarchy,
            );
        }
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
     * Get the logout URL.
     *
     * @return string The logout URL
     */
    public function getLogoutUrl()
    {
        return $this->data['logout_url'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'security';
    }
}
