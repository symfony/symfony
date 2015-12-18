<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Profiler;

use Symfony\Component\Profiler\ProfileData\ProfileDataInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\Role\RoleInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * SecurityData
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class SecurityData implements ProfileDataInterface
{
    private $enabled = false;
    private $authenticated = false;
    private $logoutUrl = null;
    private $tokenClass = null;
    private $user = '';
    private $roles = array();
    private $inheritedRoles = array();
    private $supportsRoleHierarchy = false;


    /**
     * Constructor.
     *
     * @param TokenStorageInterface $tokenStorage
     * @param RoleHierarchyInterface $roleHierarchy
     */
    public function __construct(TokenStorageInterface $tokenStorage = null, RoleHierarchyInterface $roleHierarchy = null,
                                LogoutUrlGenerator $logoutUrlGenerator = null)
    {
        $this->supportsRoleHierarchy = null !== $roleHierarchy;
        if (null !== $tokenStorage) {
            $this->enabled = true;
            if (null !== $token = $tokenStorage->getToken()) {
                $inheritedRoles = array();
                $assignedRoles = $token->getRoles();
                if (null !== $roleHierarchy) {
                    $allRoles = $roleHierarchy->getReachableRoles($assignedRoles);
                    foreach ($allRoles as $role) {
                        if (!in_array($role, $assignedRoles)) {
                            $inheritedRoles[] = $role;
                        }
                    }
                }
                $this->authenticated = $token->isAuthenticated();
                $this->tokenClass = get_class($token);
                $this->user = $token->getUsername();
                $this->roles = array_map(function (RoleInterface $role) {
                    return $role->getRole();
                }, $assignedRoles);
                $this->inheritedRoles = array_map(function (RoleInterface $role) {
                    return $role->getRole();
                }, $inheritedRoles);

                try {
                    if (null !== $logoutUrlGenerator) {
                        $this->logoutUrl = $logoutUrlGenerator->getLogoutPath();
                    }
                } catch(\Exception $e) {
                    // fail silently when the logout URL cannot be generated
                }
            }
        }
    }

    /**
     * Checks if security is enabled.
     *
     * @return bool true if security is enabled, false otherwise
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Gets the user.
     *
     * @return string The user
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Gets the roles of the user.
     *
     * @return array The roles
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * Gets the inherited roles of the user.
     *
     * @return array The inherited roles
     */
    public function getInheritedRoles()
    {
        return $this->inheritedRoles;
    }

    /**
     * Checks if the data contains information about inherited roles. Still the inherited
     * roles can be an empty array.
     *
     * @return bool true if the profile was contains inherited role information.
     */
    public function supportsRoleHierarchy()
    {
        return $this->supportsRoleHierarchy;
    }

    /**
     * Checks if the user is authenticated or not.
     *
     * @return bool true if the user is authenticated, false otherwise
     */
    public function isAuthenticated()
    {
        return $this->authenticated;
    }

    /**
     * Get the class name of the security token.
     *
     * @return string The token
     */
    public function getTokenClass()
    {
        return $this->tokenClass;
    }

    /**
     * Get the logout url.
     *
     * @return string The Logout url
     */
    public function getLogoutUrl()
    {
        return $this->logoutUrl;
    }

    public function getName()
    {
        return 'security';
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return serialize(array(
            'enabled' => $this->enabled,
            'authenticated' => $this->authenticated,
            'logout_url' => $this->logoutUrl,
            'tokenClass' => $this->tokenClass,
            'user' => $this->user,
            'roles' => $this->roles,
            'supportsRoleHierarchy' => $this->supportsRoleHierarchy,
        ));
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);
        $this->enabled = $unserialized['enabled'];
        $this->authenticated = $unserialized['authenticated'];
        $this->logoutUrl = $unserialized['logout_url'];
        $this->tokenClass = $unserialized['tokenClass'];
        $this->user = $unserialized['user'];
        $this->roles = $unserialized['roles'];
        $this->supportsRoleHierarchy = $unserialized['supportsRoleHierarchy'];
    }
}