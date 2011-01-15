<?php

namespace Symfony\Component\Security\Acl\MongoDB;

use Doctrine\MongoDB\Connection;

use Symfony\Component\Security\Acl\Domain\Acl;
use Symfony\Component\Security\Acl\Domain\Entry;
use Symfony\Component\Security\Acl\Domain\FieldEntry;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\AclNotFoundException;
use Symfony\Component\Security\Acl\Exception\NotAllAclsFoundException;
use Symfony\Component\Security\Acl\Model\AclCacheInterface;
use Symfony\Component\Security\Acl\Model\AclProviderInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;
use Symfony\Component\Security\Acl\Model\PermissionGrantingStrategyInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * An ACL provider implementation.
 *
 * This provider assumes that all ACLs share the same PermissionGrantingStrategy.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Richard D Shank <develop@zestic.com>
 */
class AclProvider implements AclProviderInterface
{
    const MAX_BATCH_SIZE = 30;

    protected $aclCache;
    protected $connection;
    protected $loadedAces;
    protected $loadedAcls;
    protected $options;
    protected $permissionGrantingStrategy;

    /**
     * Constructor
     *
     * @param Connection $connection
     * @param PermissionGrantingStrategyInterface $permissionGrantingStrategy
     * @param array $options
     * @param AclCacheInterface $aclCache
     */
    public function __construct(Connection $connection, PermissionGrantingStrategyInterface $permissionGrantingStrategy, array $options, AclCacheInterface $aclCache = null)
    {
        $this->aclCache = $aclCache;
        $this->connection = $connection;
        $this->loadedAces = array();
        $this->loadedAcls = array();
        $this->options = $options;
        $this->permissionGrantingStrategy = $permissionGrantingStrategy;
    }

    /**
     * {@inheritDoc}
     */
    function findChildren(ObjectIdentityInterface $parentOid, $directChildrenOnly = false)
    {

    }

    /**
     * {@inheritDoc}
     */
    function findAcl(ObjectIdentityInterface $oid, array $sids = array())
    {

    }

    /**
     * {@inheritDoc}
     */
    function findAcls(array $oids, array $sids = array())
    {
        
    }
}