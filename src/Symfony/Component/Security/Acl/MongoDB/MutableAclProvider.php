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

        $parentId = $this->retrieveObjectIdentityPrimaryKey($oid);
        $removable[(string)$parentId] = $parentId;
        $children = $this->findChildren($oid);
        foreach ($children as $child) {
            $childId = $child['_id'];
            $removable[(string)$childId] = $childId;
            foreach ($child['ancestors'] as $ancestor) {
                $removable[(string)$ancestor] = $ancestor;
            }
        }

        $query = array(
            '_id' => array('$in' => $removable),
        );
        $this->connection->selectCollection($this->options['oid_table_name'])->remove($query);
        $this->deleteAccessControlEntries($removable);

        // evict the ACL from the in-memory identity map
        // TODO maybe just use the id for the key in the loadedAcls
        foreach ($removable as $mongoId) {
            $id = (string)$mongoId;
            foreach ($this->loadedAcls as $type) {
                foreach ($type as $identifier) {
                    if ($id == $identifier->getId()) {
                        $oid = $identifier->getObjectIdentity();
                        // evict the ACL from any caches
                        if (null !== $this->aclCache) {
                            $this->aclCache->evictFromCacheByIdentity($oid);
                        }
                        $this->propertyChanges->offsetUnset($this->loadedAcls[$oid->getType()][$oid->getIdentifier()]);
                        unset($this->loadedAcls[$oid->getType()][$oid->getIdentifier()]);
                    }
                }
            }

        }
    }

    /**
     * {@inheritDoc}
     */
    function updateAcl(MutableAclInterface $acl)
    {
        if (!$this->propertyChanges->contains($acl)) {
            throw new \InvalidArgumentException('$acl is not tracked by this provider.');
        }

        $propertyChanges = $this->propertyChanges->offsetGet($acl);
        // check if any changes were made to this ACL
        if (0 === count($propertyChanges)) {
            return;
        }

        $sharedPropertyChanges = array();

        $this->updateObjectIdentity($acl, $propertyChanges);

        // this includes only updates of existing ACEs, but neither the creation, nor
        // the deletion of ACEs; these are tracked by changes to the ACL's respective
        // properties (classAces, classFieldAces, objectAces, objectFieldAces)
        if (isset($propertyChanges['aces'])) {
            $this->updateAces($propertyChanges['aces']);
        }

        // check properties for deleted, and created ACEs
        if (isset($propertyChanges['classAces'])) {
            $this->updateAceProperty('classAces', $propertyChanges['classAces']);
            $sharedPropertyChanges['classAces'] = $propertyChanges['classAces'];
        }
        if (isset($propertyChanges['classFieldAces'])) {
            $this->updateFieldAceProperty('classFieldAces', $propertyChanges['classFieldAces']);
            $sharedPropertyChanges['classFieldAces'] = $propertyChanges['classFieldAces'];
        }
        if (isset($propertyChanges['objectAces'])) {
            $this->updateAceProperty('objectAces', $propertyChanges['objectAces']);
        }
        if (isset($propertyChanges['objectFieldAces'])) {
            $this->updateFieldAceProperty('objectFieldAces', $propertyChanges['objectFieldAces']);
        }

        // if there have been changes to shared properties, we need to synchronize other
        // ACL instances for object identities of the same type that are already in-memory
        if (count($sharedPropertyChanges) > 0) {
            $classAcesProperty = new \ReflectionProperty('Symfony\Component\Security\Acl\Domain\Acl', 'classAces');
            $classAcesProperty->setAccessible(true);
            $classFieldAcesProperty = new \ReflectionProperty('Symfony\Component\Security\Acl\Domain\Acl', 'classFieldAces');
            $classFieldAcesProperty->setAccessible(true);

            foreach ($this->loadedAcls[$acl->getObjectIdentity()->getType()] as $sameTypeAcl) {
                if (isset($sharedPropertyChanges['classAces'])) {
                    if ($acl !== $sameTypeAcl && $classAcesProperty->getValue($sameTypeAcl) !== $sharedPropertyChanges['classAces'][0]) {
                        throw new ConcurrentModificationException('The "classAces" property has been modified concurrently.');
                    }

                    $classAcesProperty->setValue($sameTypeAcl, $sharedPropertyChanges['classAces'][1]);
                }

                if (isset($sharedPropertyChanges['classFieldAces'])) {
                    if ($acl !== $sameTypeAcl && $classFieldAcesProperty->getValue($sameTypeAcl) !== $sharedPropertyChanges['classFieldAces'][0]) {
                        throw new ConcurrentModificationException('The "classFieldAces" property has been modified concurrently.');
                    }

                    $classFieldAcesProperty->setValue($sameTypeAcl, $sharedPropertyChanges['classFieldAces'][1]);
                }
            }
        }

        $this->propertyChanges->offsetSet($acl, array());

        if (null !== $this->aclCache) {
            if (count($sharedPropertyChanges) > 0) {
                // FIXME: Currently, there is no easy way to clear the cache for ACLs
                //        of a certain type. The problem here is that we need to make
                //        sure to clear the cache of all child ACLs as well, and these
                //        child ACLs might be of a different class type.
                $this->aclCache->clearCache();
            } else {
                // if there are no shared property changes, it's sufficient to just delete
                // the cache for this ACL
                $this->aclCache->evictFromCacheByIdentity($acl->getObjectIdentity());

                foreach ($this->findChildren($acl->getObjectIdentity()) as $childOid) {
                    $this->aclCache->evictFromCacheByIdentity($childOid);
                }
            }
        }
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

    /**
     * Implementation of PropertyChangedListener
     *
     * This allows us to keep track of which values have been changed, so we don't
     * have to do a full introspection when ->updateAcl() is called.
     *
     * @param mixed $sender
     * @param string $propertyName
     * @param mixed $oldValue
     * @param mixed $newValue
     * @return void
     */
    public function propertyChanged($sender, $propertyName, $oldValue, $newValue)
    {
        if (!$sender instanceof MutableAclInterface && !$sender instanceof EntryInterface) {
            throw new \InvalidArgumentException('$sender must be an instance of MutableAclInterface, or EntryInterface.');
        }

        if ($sender instanceof EntryInterface) {
            if (null === $sender->getId()) {
                return;
            }

            $ace = $sender;
            $sender = $ace->getAcl();
        } else {
            $ace = null;
        }

        if (false === $this->propertyChanges->contains($sender)) {
            throw new \InvalidArgumentException('$sender is not being tracked by this provider.');
        }

        $propertyChanges = $this->propertyChanges->offsetGet($sender);
        if (null === $ace) {
            if (isset($propertyChanges[$propertyName])) {
                $oldValue = $propertyChanges[$propertyName][0];
                if ($oldValue === $newValue) {
                    unset($propertyChanges[$propertyName]);
                } else {
                    $propertyChanges[$propertyName] = array($oldValue, $newValue);
                }
            } else {
                $propertyChanges[$propertyName] = array($oldValue, $newValue);
            }
        } else {
            if (!isset($propertyChanges['aces'])) {
                $propertyChanges['aces'] = new \SplObjectStorage();
            }

            $acePropertyChanges = $propertyChanges['aces']->contains($ace) ? $propertyChanges['aces']->offsetGet($ace) : array();

            if (isset($acePropertyChanges[$propertyName])) {
                $oldValue = $acePropertyChanges[$propertyName][0];
                if ($oldValue === $newValue) {
                    unset($acePropertyChanges[$propertyName]);
                } else {
                    $acePropertyChanges[$propertyName] = array($oldValue, $newValue);
                }
            } else {
                $acePropertyChanges[$propertyName] = array($oldValue, $newValue);
            }

            if (count($acePropertyChanges) > 0) {
                $propertyChanges['aces']->offsetSet($ace, $acePropertyChanges);
            } else {
                $propertyChanges['aces']->offsetUnset($ace);

                if (0 === count($propertyChanges['aces'])) {
                    unset($propertyChanges['aces']);
                }
            }
        }

        $this->propertyChanges->offsetSet($sender, $propertyChanges);
    }


    /**
     * Creates the ACL for the passed object identity
     *
     * @param ObjectIdentityInterface $oid
     * @param boolean $entriesInheriting
     * @param ObjectIdentityInterface $parent
     * @return void
     */
    protected function createObjectIdentity(ObjectIdentityInterface $oid, $entriesInheriting = false, ObjectIdentityInterface $parent = null)
    {
        $data['identifier'] = $oid->getIdentifier();
        $data['type'] = $oid->getType();
        $data['entriesInheriting'] = $entriesInheriting;

        if ($parent) {
            $ancestors = array();
            $parentDocument = $this->getObjectIdentity($parent);
            if (isset($parent['ancestors'])) {
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

    /**
     * This updates the parent and ancestors in the identity
     *
     * @param AclInterface $acl
     * @param array $changes
     * @return void
     */
    protected function updateObjectIdentity(AclInterface $acl, array $changes)
    {
        if (isset($changes['entriesInheriting'])) {
            $updates['entriesInheriting'] = $changes['entriesInheriting'][1];
        }

        if (isset($changes['parentAcl'])) {

            $query = array(
                '_id' => new \MongoId($acl->getParentAcl()->getId()),
            );
            $parent = $this->connection->selectCollection($this->options['oid_table_name'])->findOne($query);

            $updates['parent'] = $parent;

            if (isset($parent['ancestors'])) {
                $ancestors = $parent['ancestors'];
            }
            $ancestors[] = $parent['_id'];
            $updates['ancestors'] = $ancestors;
        }
        if (!isset($updates)) {
            return;
        }
        $entry = array(
            '_id' => new \MongoId($acl->getId()),
        );
        $newData = array(
            '$set' => $updates,
        );

        $this->connection->selectCollection($this->options['oid_table_name'])->update($entry, $newData);
    }

    /**
     * This processes changes on an ACE related property (classAces, or objectAces).
     *
     * @param string $name
     * @param array $changes
     * @return void
     */
    protected function updateAceProperty($name, array $changes)
    {
        list($old, $new) = $changes;

        $currentIds = array();
        for ($i = 0, $c = count($new); $i < $c; $i++) {
            $ace = $new[$i];

            if (null === $ace->getId()) {
                $sid = $this->getSecurityIdentityQuery($ace->getSecurityIdentity());

                $objectIdentityId = $name === 'classAces' ? null : $ace->getAcl()->getId();

                $aceId = (string)$this->insertAccessControlEntry($objectIdentityId, null, $i, $sid, $ace->getStrategy(), $ace->getMask(), $ace->isGranting(), $ace->isAuditSuccess(), $ace->isAuditFailure());
                $this->loadedAces[$aceId] = $ace;

                $aceIdProperty = new \ReflectionProperty($ace, 'id');
                $aceIdProperty->setAccessible(true);
                $aceIdProperty->setValue($ace, $aceId);
            } else {
                $currentIds[$ace->getId()] = true;
            }
        }

        for ($i = 0, $c = count($old); $i < $c; $i++) {
            $ace = $old[$i];

            if (!isset($currentIds[$ace->getId()])) {
                $this->deleteAccessControlEntry($ace->getId());
                unset($this->loadedAces[$ace->getId()]);
            }
        }
    }

    /**
     * Create an array of the security identity for inserting in the document
     *
     * @param SecurityIdentityInterface $sid
     * @throws \InvalidArgumentException
     * @return array
     */
    protected function getSecurityIdentityQuery(SecurityIdentityInterface $sid)
    {
        if ($sid instanceof UserSecurityIdentity) {
            return array('username' => $sid->getUsername(), 'class' => $sid->getClass());
        } else if ($sid instanceof RoleSecurityIdentity) {
            return array('role' => $sid->getRole());
        } else {
            throw new \InvalidArgumentException('$sid must either be an instance of UserSecurityIdentity, or RoleSecurityIdentity.');
        }
    }

    /**
     * Insert an ACE into the collection.
     *
     * @param integer|null $objectIdentityId
     * @param string|null $field
     * @param integer $aceOrder
     * @param integer $securityIdentityId
     * @param string $strategy
     * @param integer $mask
     * @param Boolean $granting
     * @param Boolean $auditSuccess
     * @param Boolean $auditFailure
     * @return MongoId
     */
    protected function insertAccessControlEntry($objectIdentityId, $field, $aceOrder, $securityIdentity, $strategy, $mask, $granting, $auditSuccess, $auditFailure)
    {
        $criteria = array(
            'aceOrder' => $aceOrder,
            'securityIdentity' => $securityIdentity,
            'mask' => $mask,
            'granting' => $granting,
            'grantingStrategy' => $strategy,
            'auditSuccess' => $auditSuccess,
            'auditFailure' => $auditFailure,
        );

        if (isset($objectIdentityId)) {
            $criteria['objectIdentity'] = array(
                '$ref' => $this->options['oid_table_name'],
                '$id' => new \MongoId($objectIdentityId),
            );
        }
        if (isset($field)) {
            $criteria['fieldName'] = $field;
        }
        $this->connection->selectCollection($this->options['entry_table_name'])->insert($criteria);
        return $criteria['_id'];
    }

    /**
     * Remove an entry from the collection
     *
     * @param string $id
     */
    protected function deleteAccessControlEntry($id)
    {
        $criteria = array(
            '_id' => new \MongoId($id),
        );
        $this->connection->selectCollection($this->options['entry_table_name'])->remove($criteria);
    }

    /**
     * Persists the changes which were made to ACEs to the database.
     *
     * @param \SplObjectStorage $aces
     * @return void
     */
    protected function updateAces(\SplObjectStorage $aces)
    {
        foreach ($aces as $ace)
        {
            $update = array();
            $propertyChanges = $aces->offsetGet($ace);
            if (isset($propertyChanges['mask'])) {
                $update['mask'] = $propertyChanges['mask'][1];
            }
            if (isset($propertyChanges['strategy'])) {
                $update['strategy'] = $propertyChanges['strategy'];
            }
            if (isset($propertyChanges['aceOrder'])) {
                $update['aceOrder'] = $propertyChanges['aceOrder'][1];
            }
            if (isset($propertyChanges['auditSuccess'])) {
                $update['auditSuccess'] = $propertyChanges['auditSuccess'][1];
            }
            if (isset($propertyChanges['auditFailure'])) {
                $update['auditFailure'] = $propertyChanges['auditFailure'][1];
            }
            $criteria = array(
                '_id' => new \MongoId($ace->getId()),
            );
            $this->connection->selectCollection($this->options['entry_table_name'])->update($criteria, array('$set' => $update));
        }
    }
}