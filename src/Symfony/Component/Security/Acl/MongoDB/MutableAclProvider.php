<?php

namespace Symfony\Component\Security\Acl\MongoDB;

use Doctrine\Common\PropertyChangedListener;
use Doctrine\MongoDB\Connection;

use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\AclAlreadyExistsException;
use Symfony\Component\Security\Acl\Exception\ConcurrentModificationException;
use Symfony\Component\Security\Acl\Exception\Exception;
use Symfony\Component\Security\Acl\Model\AclCacheInterface;
use Symfony\Component\Security\Acl\Model\AclInterface;
use Symfony\Component\Security\Acl\Model\EntryInterface;
use Symfony\Component\Security\Acl\Model\MutableAclInterface;
use Symfony\Component\Security\Acl\Model\MutableAclProviderInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;
use Symfony\Component\Security\Acl\Model\PermissionGrantingStrategyInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * An implementation of the MutableAclProviderInterface using Doctrine DBAL.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Richard D Shank <develop@zestic.com>
 */
class MutableAclProvider extends AclProvider implements MutableAclProviderInterface, PropertyChangedListener
{
    protected $propertyChanges;

    /**
     * {@inheritDoc}
     */
    public function __construct(Connection $connection, PermissionGrantingStrategyInterface $permissionGrantingStrategy, array $options, AclCacheInterface $aclCache = null)
    {
        parent::__construct($connection, $permissionGrantingStrategy, $options, $aclCache);

        $this->propertyChanges = new \SplObjectStorage();
    }

    /**
     * {@inheritDoc}
     */
    function createAcl(ObjectIdentityInterface $oid)
    {

    }

    /**
     * {@inheritDoc}
     */
    function deleteAcl(ObjectIdentityInterface $oid)
    {

    }

    /**
     * {@inheritDoc}
     */
    function updateAcl(MutableAclInterface $acl)
    {

    }

    function propertyChanged($sender, $propertyName, $oldValue, $newValue)
    {

    }
}