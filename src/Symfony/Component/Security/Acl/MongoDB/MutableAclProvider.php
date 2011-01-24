<?php

namespace Symfony\Component\Security\Acl\MongoDB;

use Doctrine\Common\PropertyChangedListener;
use Doctrine\MongoDB\Database;

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
    public function __construct(Database $database, PermissionGrantingStrategyInterface $permissionGrantingStrategy, array $options, AclCacheInterface $aclCache = null)
    {
        parent::__construct($database, $permissionGrantingStrategy, $options, $aclCache);

        $this->propertyChanges = new \SplObjectStorage();
    }

    /**
     * {@inheritDoc}
     */
    function createAcl(ObjectIdentityInterface $oid)
    {
        $key = $this->retrieveObjectIdentityPrimaryKey($oid);
        if ($this->retrieveObjectIdentityPrimaryKey($oid)) {
            throw new AclAlreadyExistsException(sprintf('%s is already associated with an ACL.', $oid));
        }

        $this->createObjectIdentity($oid, true);

        // re-read the ACL from the database to ensure proper caching, etc.
        return $this->findAcl($oid);
    }

    /**
     * {@inheritDoc}
     */
    function deleteAcl(ObjectIdentityInterface $oid)
    {
        // TODO: safe options
        $oidPK = $this->retrieveObjectIdentityPrimaryKey($oid);

        $removableIds = $this->findChildrenIds($oidPK);
        $removableIds[] = $oidPK;

        $query = array(
            '_id' => array('$in'=>$removableIds),
        );
        $this->connection->selectCollection($this->options['oid_table_name'])->remove($query);
        $this->deleteAccessControlEntries($removableIds);


        // evict the ACL from the in-memory identity map
        if (isset($this->loadedAcls[$oid->getType()][$oid->getIdentifier()])) {
            $this->propertyChanges->offsetUnset($this->loadedAcls[$oid->getType()][$oid->getIdentifier()]);
            unset($this->loadedAcls[$oid->getType()][$oid->getIdentifier()]);
        }

        // evict the ACL from any caches
        if (null !== $this->aclCache) {
            $this->aclCache->evictFromCacheByIdentity($oid);
        }
    }

    /**
     * {@inheritDoc}
     */
    function updateAcl(MutableAclInterface $acl)
    {

    }

    /**
     * {@inheritDoc}
     */
    public function findAcls(array $oids, array $sids = array())
    {
        $result = parent::findAcls($oids, $sids);

        foreach ($result as $oid) {
            $acl = $result->offsetGet($oid);

            if (false === $this->propertyChanges->contains($acl) && $acl instanceof MutableAclInterface) {
                $acl->addPropertyChangedListener($this);
                $this->propertyChanges->attach($acl, array());
            }

            $parentAcl = $acl->getParentAcl();
            while (null !== $parentAcl) {
                if (false === $this->propertyChanges->contains($parentAcl) && $acl instanceof MutableAclInterface) {
                    $parentAcl->addPropertyChangedListener($this);
                    $this->propertyChanges->attach($parentAcl, array());
                }

                $parentAcl = $parentAcl->getParentAcl();
            }
        }

        return $result;
    }

    function propertyChanged($sender, $propertyName, $oldValue, $newValue)
    {

    }


    /**
     * Creates the ACL for the passed object identity
     *
     * @param ObjectIdentityInterface $oid
     * @param boolean $entriesInheriting
     * @param ObjectIdentityInterface $parent
     * @return void
     */
    protected function createObjectIdentity(ObjectIdentityInterface $oid, $entriesInheriting, ObjectIdentityInterface $parent=null)
    {
        $data['identifier']        = $oid->getIdentifier();
        $data['type']              = $oid->getType();
        $data['entriesInheriting'] = $entriesInheriting;

        if($parent) {
            $ancestors = array();
            $parentDocument = $this->getObjectIdentity($parent);
            if( isset($parent['ancestors'])) {
                $ancestors = $parentDocument['ancestors'];
            }
            $ancestors[] = $parentDocument['_id'];
            $data['parent'] = $parentDocument;
            $data['ancestors'] = $ancestors;
        }

        // TODO: safe options
        return $this->connection->selectCollection($this->options['oid_table_name'])->insert($data);
    }

    /**
     * Deletes all ACEs for the given object identity primary key.
     *
     * @param array $removableIds MongoId
     * @return void
     */
    protected function deleteAccessControlEntries($removableIds)
    {
        // TODO: safe options
        $query = array(
            'objectIdentity' => array('$in' => $removableIds),
        );
        $this->connection->selectCollection($this->options['entry_table_name'])->remove($query);
    }
}