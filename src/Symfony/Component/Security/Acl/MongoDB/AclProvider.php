<?php

namespace Symfony\Component\Security\Acl\MongoDB;

use Doctrine\MongoDB\Database;
use Doctrine\MongoDB\Cursor;

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
     * @param Database $database
     * @param PermissionGrantingStrategyInterface $permissionGrantingStrategy
     * @param array $options
     * @param AclCacheInterface $aclCache
     */
    public function __construct(Database $database, PermissionGrantingStrategyInterface $permissionGrantingStrategy, array $options, AclCacheInterface $aclCache = null)
    {
        $this->aclCache = $aclCache;
        $this->connection = $database;
        $this->loadedAces = array();
        $this->loadedAcls = array();
        $this->options = $options;
        $this->permissionGrantingStrategy = $permissionGrantingStrategy;
    }

    /**
     * {@inheritDoc}
     */
    public function findChildren(ObjectIdentityInterface $parentOid, $directChildrenOnly = false)
    {
        $parentId = $this->retrieveObjectIdentityPrimaryKey($parentOid);

        if ($directChildrenOnly) {
            $query = array(
                "parent" => $parentId,
            );
        } else {
            $query = array(
                "ancestors" => $parentId,
            );
        }
        $children = array();
        foreach ($this->connection->selectCollection($this->options['oid_table_name'])->find($query) as $data) {
            $children[] = $data;
        }
        return $children;
    }

    /**
     * {@inheritDoc}
     */
    function findAcl(ObjectIdentityInterface $oid, array $sids = array())
    {
        return $this->findAcls(array($oid), $sids)->offsetGet($oid);
    }

    /**
     * {@inheritDoc}
     */
    function findAcls(array $oids, array $sids = array())
    {
        $result = new \SplObjectStorage();
        $currentBatch = array();
        $oidLookup = array();

        for ($i = 0, $c = count($oids); $i < $c; $i++) {
            $oid = $oids[$i];
            $oidLookupKey = $oid->getIdentifier() . $oid->getType();
            $oidLookup[$oidLookupKey] = $oid;
            $aclFound = false;

            // check if result already contains an ACL
            if ($result->contains($oid)) {
                $aclFound = true;
            }

            // check if this ACL has already been hydrated
            if (!$aclFound && isset($this->loadedAcls[$oid->getType()][$oid->getIdentifier()])) {
                $acl = $this->loadedAcls[$oid->getType()][$oid->getIdentifier()];

                if (!$acl->isSidLoaded($sids)) {
                    // FIXME: we need to load ACEs for the missing SIDs. This is never
                    //        reached by the default implementation, since we do not
                    //        filter by SID
                    throw new \RuntimeException('This is not supported by the default implementation.');
                } else {
                    $result->attach($oid, $acl);
                    $aclFound = true;
                }
            }

            // check if we can locate the ACL in the cache
            if (!$aclFound && null !== $this->aclCache) {
                $acl = $this->aclCache->getFromCacheByIdentity($oid);

                if (null !== $acl) {
                    if ($acl->isSidLoaded($sids)) {
                        // check if any of the parents has been loaded since we need to
                        // ensure that there is only ever one ACL per object identity
                        $parentAcl = $acl->getParentAcl();
                        while (null !== $parentAcl) {
                            $parentOid = $parentAcl->getObjectIdentity();

                            if (isset($this->loadedAcls[$parentOid->getType()][$parentOid->getIdentifier()])) {
                                $acl->setParentAcl($this->loadedAcls[$parentOid->getType()][$parentOid->getIdentifier()]);
                                break;
                            } else {
                                $this->loadedAcls[$parentOid->getType()][$parentOid->getIdentifier()] = $parentAcl;
                                $this->updateAceIdentityMap($parentAcl);
                            }

                            $parentAcl = $parentAcl->getParentAcl();
                        }

                        $this->loadedAcls[$oid->getType()][$oid->getIdentifier()] = $acl;
                        $this->updateAceIdentityMap($acl);
                        $result->attach($oid, $acl);
                        $aclFound = true;
                    } else {
                        $this->aclCache->evictFromCacheByIdentity($oid);

                        foreach ($this->findChildren($oid) as $childOid) {
                            $this->aclCache->evictFromCacheByIdentity($childOid);
                        }
                    }
                }
            }

            // looks like we have to load the ACL from the database
            if (!$aclFound) {
                $currentBatch[] = $oid;
            }

            // Is it time to load the current batch?
            if ((self::MAX_BATCH_SIZE === count($currentBatch) || ($i + 1) === $c) && count($currentBatch) > 0) {
                $loadedBatch = $this->lookupObjectIdentities($currentBatch, $sids, $oidLookup);

                foreach ($loadedBatch as $loadedOid) {
                    $loadedAcl = $loadedBatch->offsetGet($loadedOid);

                    if (null !== $this->aclCache) {
                        $this->aclCache->putInCache($loadedAcl);
                    }

                    if (isset($oidLookup[$loadedOid->getIdentifier() . $loadedOid->getType()])) {
                        $result->attach($loadedOid, $loadedAcl);
                    }
                }

                $currentBatch = array();
            }
        }

        // check that we got ACLs for all the identities
        foreach ($oids as $oid) {
            if (!$result->contains($oid)) {
                if (1 === count($oids)) {
                    throw new AclNotFoundException(sprintf('No ACL found for %s.', $oid));
                }

                $partialResultException = new NotAllAclsFoundException('The provider could not find ACLs for all object identities.');
                $partialResultException->setPartialResult($result);

                throw $partialResultException;
            }
        }

        return $result;
    }

    /**
     * This method is called for object identities which could not be retrieved
     * from the cache, and for which thus a database query is required.
     *
     * @param array $batch
     * @param array $sids
     * @param array $oidLookup
     * @return \SplObjectStorage mapping object identities to ACL instances
     */
    protected function lookupObjectIdentities(array $batch, array $sids, array $oidLookup)
    {
        // FIXME: add support for filtering by sids (right now we select all sids)
        $objIdentities = $this->getObjectIdentities($batch);
        if (!$objIdentities->hasNext()) {
            throw new AclNotFoundException('There is no ACL for the given object identity.');
        }
        $oids = $this->getOIDSet($objIdentities, $sids);
        $entryQuery = array('objectIdentity.$id' => array('$in' => $oids));
        $entryCursor = $this->connection->selectCollection($this->options['entry_table_name'])->find($entryQuery);
        $oidQuery = array('_id' => array('$in' => $oids));
        $oidCursor = $this->connection->selectCollection($this->options['oid_table_name'])->find($oidQuery);
        return $this->hydrateObjectIdentities($entryCursor, $oidCursor, $oidLookup, $sids);
    }

    /**
     * Retrieves the documents associated with the values in the batch
     *
     * @param array $batch ObjectIdentity
     * @return Doctrine\MongoDB\Cursor
     */
    protected function getObjectIdentities(array &$batch)
    {
        $batchSet = array();
        $dataSet = new \SplObjectStorage();
        for ($i = 0, $c = count($batch); $i < $c; $i++) {
            $batchSet[] = $query = array(
                "identifier" => $batch[$i]->getIdentifier(),
                "type" => $batch[$i]->getType(),
            );
        }
        $query = array('$or' => $batchSet);

        return $this->connection->selectCollection($this->options['oid_table_name'])->find($query);
    }

    /**
     * Retrieve the document associated with the values in the ObjectIdentity
     *
     * @param ObjectIdentity $oid
     * @return Doctrine\MongoDB\Cursor
     */
    protected function getObjectIdentity(ObjectIdentity $oid)
    {
        $query = array(
            "identifier" => $oid->getIdentifier(),
            "type" => $oid->getType(),
        );

        return $this->connection->selectCollection($this->options['oid_table_name'])->findOne($query);
    }

    /**
     * Constructs the query used for looking up object identities and associated
     * ACEs, and security identities.
     *
     * @param Cursor $objectCursor
     * @param array $sids
     * @throws AclNotFoundException
     * @return array $query
     */
    protected function getOIDSet(Cursor $objectCursor, array $sids)
    {
        // FIXME: add support for filtering by sids (right now we select all sids)
        $oids = array();
        $objectData = iterator_to_array($objectCursor);
        foreach ($objectData as $object) {
            $oids[] = $object['_id'];
            if (isset($object['ancestors'])) {
                $oids = array_merge($oids, $object['ancestors']);
            }
        }
        return array_unique($oids);
    }

    /**
     * This method is called to hydrate ACLs and ACEs.
     *
     * This method was designed for performance; thus, a lot of code has been
     * inlined at the cost of readability, and maintainability.
     *
     * Keep in mind that changes to this method might severely reduce the
     * performance of the entire ACL system.
     *
     * @param Cursor $cursor
     * @param Cursor $objectIdentities
     * @param array $oidLookup
     * @param array $sids
     * @throws \RuntimeException
     * @return \SplObjectStorage
     */
    protected function hydrateObjectIdentities(Cursor $entryCursor, Cursor $objectCursor, array $oidLookup, array $sids)
    {
        $parentIdToFill = new \SplObjectStorage();
        $acls = $aces = $emptyArray = array();
        $oidCache = $oidLookup;
        $result = new \SplObjectStorage();
        $loadedAces =& $this->loadedAces;
        $loadedAcls =& $this->loadedAcls;
        $permissionGrantingStrategy = $this->permissionGrantingStrategy;

        // we need these to set protected properties on hydrated objects
        $aclReflection = new \ReflectionClass('Symfony\Component\Security\Acl\Domain\Acl');
        $aclClassAcesProperty = $aclReflection->getProperty('classAces');
        $aclClassAcesProperty->setAccessible(true);
        $aclClassFieldAcesProperty = $aclReflection->getProperty('classFieldAces');
        $aclClassFieldAcesProperty->setAccessible(true);
        $aclObjectAcesProperty = $aclReflection->getProperty('objectAces');
        $aclObjectAcesProperty->setAccessible(true);
        $aclObjectFieldAcesProperty = $aclReflection->getProperty('objectFieldAces');
        $aclObjectFieldAcesProperty->setAccessible(true);
        $aclParentAclProperty = $aclReflection->getProperty('parentAcl');
        $aclParentAclProperty->setAccessible(true);


        $entries = array();

        /**
         * using iterator_to_array is faster, but could cause potential problems with low memory, high data set
         * if it does in the foreach()
         * use $entryCursor instead of $entryData
         * use $objectCursor instead of $objectData
         *
         * TODO: should this be configurable?
         */
        $entryData = iterator_to_array($entryCursor);
        foreach ($entryData as $entry) {
            $objectId = (string)$entry['objectIdentity']['$id'];
            $eid = (string)$entry['_id'];
            $entries[$objectId][$eid] = $entry;
        }

        $objectData = iterator_to_array($objectCursor);
        foreach ($objectData as $curObject) {
            $aclId = (string)$curObject['_id'];
            $oid[$aclId] = $curObject;

            if (!isset($entries[$aclId])) {
                $parent = $curObject;
                while ((string)$parent['_id'] != $aclId && isset($parent['parent'])) {
                    if (isset($parent['parent'])) {
                        $parent = $parent['parent'];
                    }
                }
                $entries[$aclId][0] = array(
                    'objectIdentity' => $parent,
                    'fieldName' => null,
                    'aceOrder' => null,
                    'grantingStrategy' => null,
                    'mask' => null,
                    'granting' => null,
                    'auditFailure' => null,
                    'auditSuccess' => null,
                );
            }
            foreach ($entries[$aclId] as $aceId => $entry) {
                $objectIdentity = $oid[$aclId];
                $classType = $objectIdentity['type'];
                $objectIdentifier = $objectIdentity['identifier'];
                $fieldName = isset($entry['fieldName']) ? $entry['fieldName'] : null;
                $aceOrder = $entry['aceOrder'];
                $grantingStrategy = $entry['grantingStrategy'];
                $mask = (integer)$entry['mask'];
                $granting = $entry['granting'];
                $auditFailure = $entry['auditFailure'];
                $auditSuccess = $entry['auditSuccess'];

                // has the ACL been hydrated during this hydration cycle?
                if (isset($acls[$aclId])) {
                    $acl = $acls[$aclId];
                }
                    // has the ACL been hydrated during any previous cycle, or was possibly loaded
                    // from cache?
                else if (isset($loadedAcls[$classType][$objectIdentifier])) {
                    $acl = $loadedAcls[$classType][$objectIdentifier];

                    // keep reference in local array (saves us some hash calculations)
                    $acls[$aclId] = $acl;

                    // attach ACL to the result set; even though we do not enforce that every
                    // object identity has only one instance, we must make sure to maintain
                    // referential equality with the oids passed to findAcls()
                    $oidLookupKey = $objectIdentifier . $classType;
                    if (!isset($oidCache[$oidLookupKey])) {
                        $oidCache[$oidLookupKey] = $acl->getObjectIdentity();
                    }
                    $result->attach($oidCache[$oidLookupKey], $acl);
                }

                    // so, this hasn't been hydrated yet
                else {
                    // create object identity if we haven't done so yet
                    $oidLookupKey = $objectIdentifier . $classType;
                    if (!isset($oidCache[$oidLookupKey])) {
                        $oidCache[$oidLookupKey] = new ObjectIdentity($objectIdentifier, $classType);
                    }

                    $acl = new Acl($aclId, $oidCache[$oidLookupKey], $permissionGrantingStrategy, $emptyArray, !!$objectIdentity['entriesInheriting']);

                    // keep a local, and global reference to this ACL
                    $loadedAcls[$classType][$objectIdentifier] = $acl;
                    $acls[$aclId] = $acl;

                    // try to fill in parent ACL, or defer until all ACLs have been hydrated
                    if (isset($objectIdentity['parent'])) {
                        $parentObjectIdentityId = (string)$objectIdentity['parent']['_id'];
                        if (isset($acls[$parentObjectIdentityId])) {
                            $aclParentAclProperty->setValue($acl, $acls[$parentObjectIdentityId]);
                        } else {
                            $parentIdToFill->attach($acl, $parentObjectIdentityId);
                        }
                    }

                    $result->attach($oidCache[$oidLookupKey], $acl);
                }

                // check if this row contains an ACE record
                if (0 !== $aceId) {
                    // have we already hydrated ACEs for this ACL?
                    if (!isset($aces[$aclId])) {
                        $aces[$aclId] = array($emptyArray, $emptyArray, $emptyArray, $emptyArray);
                    }

                    // has this ACE already been hydrated during a previous cycle, or
                    // possible been loaded from cache?
                    // It is important to only ever have one ACE instance per actual row since
                    // some ACEs are shared between ACL instances
                    if (!isset($loadedAces[$aceId])) {
                        if (isset($entry['securityIdentity']['username'])) {
                            $securityId = '1' . $entry['securityIdentity']['class'];
                            if (!isset($sids[$securityId])) {
                                $sids[$securityId] = new UserSecurityIdentity(
                                    $entry['securityIdentity']['username'],
                                    $entry['securityIdentity']['class']
                                );
                            }
                        } else {
                            $securityId = '0' . $entry['securityIdentity']['role'];
                            if (!isset($sids[$securityId])) {
                                $sids[$securityId] = new RoleSecurityIdentity($entry['securityIdentity']['role']);
                            }
                        }

                        if (null === $fieldName) {
                            $loadedAces[$aceId] = new Entry($aceId, $acl, $sids[$securityId], $grantingStrategy, (integer)$mask, !!$granting, !!$auditFailure, !!$auditSuccess);
                        } else {
                            $loadedAces[$aceId] = new FieldEntry($aceId, $acl, $fieldName, $sids[$securityId], $grantingStrategy, (integer)$mask, !!$granting, !!$auditFailure, !!$auditSuccess);
                        }
                    }
                    $ace = $loadedAces[$aceId];

                    // assign ACE to the correct property
                    if (null === $objectIdentity) {
                        if (null === $fieldName) {
                            $aces[$aclId][0][$aceOrder] = $ace;
                        } else {
                            $aces[$aclId][1][$fieldName][$aceOrder] = $ace;
                        }
                    } else {
                        if (null === $fieldName) {
                            $aces[$aclId][2][$aceOrder] = $ace;
                        } else {
                            $aces[$aclId][3][$fieldName][$aceOrder] = $ace;
                        }
                    }
                }
            }
        }

        // We do not sort on database level since we only want certain subsets to be sorted,
        // and we are going to read the entire result set anyway.
        // Sorting on DB level increases query time by an order of magnitude while it is
        // almost negligible when we use PHPs array sort functions.
        foreach ($aces as $aclId => $aceData) {
            $acl = $acls[$aclId];

            ksort($aceData[0]);
            $aclClassAcesProperty->setValue($acl, $aceData[0]);

            foreach (array_keys($aceData[1]) as $fieldName) {
                ksort($aceData[1][$fieldName]);
            }
            $aclClassFieldAcesProperty->setValue($acl, $aceData[1]);

            ksort($aceData[2]);
            $aclObjectAcesProperty->setValue($acl, $aceData[2]);

            foreach (array_keys($aceData[3]) as $fieldName) {
                ksort($aceData[3][$fieldName]);
            }
            $aclObjectFieldAcesProperty->setValue($acl, $aceData[3]);
        }

        // fill-in parent ACLs where this hasn't been done yet cause the parent ACL was not
        // yet available
        $processed = 0;
        foreach ($parentIdToFill as $acl)
        {
            $parentId = $parentIdToFill->offsetGet($acl);

            // let's see if we have already hydrated this
            if (isset($acls[$parentId])) {
                $aclParentAclProperty->setValue($acl, $acls[$parentId]);
                $processed += 1;

                continue;
            }
        }

        // reset reflection changes
        $aclClassAcesProperty->setAccessible(false);
        $aclClassFieldAcesProperty->setAccessible(false);
        $aclObjectAcesProperty->setAccessible(false);
        $aclObjectFieldAcesProperty->setAccessible(false);
        $aclParentAclProperty->setAccessible(false);

        // this should never be true if the database integrity hasn't been compromised
        if ($processed < count($parentIdToFill)) {
            throw new \RuntimeException('Not all parent ids were populated. This implies an integrity problem.');
        }

        return $result;
    }

    /**
     * Returns the primary key of the passed object identity.
     *
     * @param ObjectIdentityInterface $oid
     * @return integer
     */
    protected function retrieveObjectIdentityPrimaryKey(ObjectIdentityInterface $oid)
    {
        $query = array(
            "identifier" => $oid->getIdentifier(),
            "type" => $oid->getType(),
        );

        $fields = array(
            "_id" => true,
        );
        $id = $this->connection->selectCollection($this->options['oid_table_name'])->findOne($query, $fields);
        return $id ? array_pop($id) : null;
    }
}