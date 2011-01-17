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
     * @param Connection $connection
     * @param PermissionGrantingStrategyInterface $permissionGrantingStrategy
     * @param array $options
     * @param AclCacheInterface $aclCache
     */
    public function __construct(Database $connection, PermissionGrantingStrategyInterface $permissionGrantingStrategy, array $options, AclCacheInterface $aclCache = null)
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

        for ($i=0,$c=count($oids); $i<$c; $i++) {
            $oid = $oids[$i];
            $oidLookupKey = $oid->getIdentifier().$oid->getType();
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
//var_dump($oid);var_dump($oidLookup);exit;
            // Is it time to load the current batch?
            if ((self::MAX_BATCH_SIZE === count($currentBatch) || ($i + 1) === $c) && count($currentBatch) > 0) {
                $loadedBatch = $this->lookupObjectIdentities($currentBatch, $sids, $oidLookup);

                foreach ($loadedBatch as $loadedOid) {
                    $loadedAcl = $loadedBatch->offsetGet($loadedOid);

                    if (null !== $this->aclCache) {
                        $this->aclCache->putInCache($loadedAcl);
                    }

                    if (isset($oidLookup[$loadedOid->getIdentifier().$loadedOid->getType()])) {
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
     * @return \SplObjectStorage mapping object identites to ACL instances
     */
    protected function lookupObjectIdentities(array $batch, array $sids, array $oidLookup)
    {
        /*
        needed fields
                o.id as acl_id,
                o.object_identity,
                o.parent_object_identity_id,
                o.entries_inheriting,
                e.id as ace_id,
                e.object_identity_id, // unless this is specifically used , it should reference the above o ie. e.object_identity
                e.field_name,
                e.ace_order,
                e.mask,
                e.granting,
                e.granting_strategy,
                e.audit_success,
                e.audit_failure,
                e.security_identity
 basically, its pulling everything from acl_entries except ancestors using ancestors cursor to look for for acl_entries referencing object_identities

 */
        $fields = array(
            'ancestors' => false
        );
        $query = $this->getLookupQuery($batch, $sids);
        $cursor = $this->connection->selectCollection($this->options['entry_table_name'])->find($query);//, $fields);
$size = $cursor->count();
        return $this->hydrateObjectIdentities($cursor, $oidLookup, $sids);
    }

    /**
     * Retrieves all the ids which need to be queried from the database
     * including the ids of parent ACLs.
     *
     * @param array $batch
     * @return Doctrine\MongoDB\Cursor
     */
    protected function getAncestors(array &$batch)
    {
        $batchSet = array();
        for ($i=0,$c=count($batch); $i<$c; $i++) {
            $batchSet[] = array(
                "object_identity.identifier" => $batch[$i]->getIdentifier(),
                "object_identity.type" => $batch[$i]->getType(),
            );
        }
        $query = array('$or'=> $batchSet);
        $fields = array('ancestors'=> true); // get the ancestors only

        return $this->connection->selectCollection($this->options['oid_table_name'])->find($query, $fields);
    }

    /**
     * Constructs the query used for looking up object identities and associated
     * ACEs, and security identities.
     *
     * @param array $batch
     * @param array $sids
     * @throws AclNotFoundException
     * @return array $query
     */
    protected function getLookupQuery(array $batch, array $sids)
    {
        // FIXME: add support for filtering by sids (right now we select all sids)

        $cursor = $this->getAncestors($batch);
        if (0 === $cursor->count()) {
            throw new AclNotFoundException('There is no ACL for the given object identity.');
        }
        $ancestorIds = array();
        foreach($cursor as $result) {
            $ancestorIds[] = $result['_id'];
            $ancestorIds = array_merge($ancestorIds, $result['ancestors']);
        }

        return array("object_identity._id" => array('$in'=>$ancestorIds));
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
     * @param array $oidLookup
     * @param array $sids
     * @throws \RuntimeException
     * @return \SplObjectStorage
     */
    protected function hydrateObjectIdentities(Cursor $cursor, array $oidLookup, array $sids) {
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

        // fetchAll() consumes more memory than consecutive calls to fetch(),
        // but it is faster
        $size = $cursor->count();
        if(1<$size) {
            $cursor->batchNum($size);
        }
        foreach ($cursor->current() as $data) {
            list($aclId,
                 $objectIdentity,
                 $parentObjectIdentityId,
                 $entriesInheriting,
                 $aceId,
                 $objectIdentityId,
                 $fieldName,
                 $aceOrder,
                 $mask,
                 $granting,
                 $grantingStrategy,
                 $auditSuccess,
                 $auditFailure,
                 $securityIdentity) = $data;

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
                $oidLookupKey = $objectIdentity['_id'];
                if (!isset($oidCache[$oidLookupKey])) {
                    $oidCache[$oidLookupKey] = $acl->getObjectIdentity();
                }
                $result->attach($oidCache[$oidLookupKey], $acl);
            }

            // so, this hasn't been hydrated yet
            else {
                // create object identity if we haven't done so yet
                $oidLookupKey = $objectIdentity['_id'];
                if (!isset($oidCache[$oidLookupKey])) {
                    $oidCache[$oidLookupKey] = new ObjectIdentity($objectIdentifier, $classType);
                }

                $acl = new Acl((integer) $aclId, $oidCache[$oidLookupKey], $permissionGrantingStrategy, $emptyArray, !!$entriesInheriting);

                // keep a local, and global reference to this ACL
                $loadedAcls[$classType][$objectIdentifier] = $acl;
                $acls[$aclId] = $acl;

                // try to fill in parent ACL, or defer until all ACLs have been hydrated
                if (null !== $parentObjectIdentityId) {
                    if (isset($acls[$parentObjectIdentityId])) {
                        $aclParentAclProperty->setValue($acl, $acls[$parentObjectIdentityId]);
                    } else {
                        $parentIdToFill->attach($acl, $parentObjectIdentityId);
                    }
                }

                $result->attach($oidCache[$oidLookupKey], $acl);
            }

            // check if this row contains an ACE record
            if (null !== $aceId) {
                // have we already hydrated ACEs for this ACL?
                if (!isset($aces[$aclId])) {
                    $aces[$aclId] = array($emptyArray, $emptyArray, $emptyArray, $emptyArray);
                }

                // has this ACE already been hydrated during a previous cycle, or
                // possible been loaded from cache?
                // It is important to only ever have one ACE instance per actual row since
                // some ACEs are shared between ACL instances
                if (!isset($loadedAces[$aceId])) {
                    if (!isset($sids[$key = ($username?'1':'0').$securityIdentifier])) {
                        if ($username) {
                            $sids[$key] = new UserSecurityIdentity(
                                substr($securityIdentifier, 1 + $pos = strpos($securityIdentifier, '-')),
                                substr($securityIdentifier, 0, $pos)
                            );
                        } else {
                            $sids[$key] = new RoleSecurityIdentity($securityIdentifier);
                        }
                    }

                    if (null === $fieldName) {
                        $loadedAces[$aceId] = new Entry((integer) $aceId, $acl, $sids[$key], $grantingStrategy, (integer) $mask, !!$granting, !!$auditFailure, !!$auditSuccess);
                    } else {
                        $loadedAces[$aceId] = new FieldEntry((integer) $aceId, $acl, $fieldName, $sids[$key], $grantingStrategy, (integer) $mask, !!$granting, !!$auditFailure, !!$auditSuccess);
                    }
                }
                $ace = $loadedAces[$aceId];

                // assign ACE to the correct property
                if (null === $objectIdentityId) {
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

}