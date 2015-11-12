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

use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Logout\LogoutUrlGenerator;

/**
 * SecurityDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class SecurityDataCollector implements DataCollectorInterface
{
    private $tokenStorage;
    private $roleHierarchy;
    private $logoutUrlGenerator;

    /**
     * Constructor.
     *
     * @param TokenStorageInterface|null  $tokenStorage
     * @param RoleHierarchyInterface|null $roleHierarchy
     * @param LogoutUrlGenerator|null     $logoutUrlGenerator
     */
    public function __construct(TokenStorageInterface $tokenStorage = null, RoleHierarchyInterface $roleHierarchy = null,
                                LogoutUrlGenerator $logoutUrlGenerator = null)
    {
        $this->tokenStorage = $tokenStorage;
        $this->roleHierarchy = $roleHierarchy;
        $this->logoutUrlGenerator = $logoutUrlGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        return new SecurityData($this->tokenStorage, $this->roleHierarchy, $this->logoutUrlGenerator);
    }
}
