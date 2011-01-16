<?php

namespace Symfony\Component\Security\Acl\MongoDB;

use Doctrine\MongoDB\Database;

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
        $query = $this->getLookupQuery($batch, $sids);

 //       $stmt = $this->connection->executeQuery($sql);

//        return $this->hydrateObjectIdentities($stmt, $oidLookup, $sids);
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
                "object_identifier.identifier" => $batch[$i]->getIdentifier(),
                "object_identifier.type" => $batch[$i]->getType(),
            );
        }
        $query = array('$or'=> $batchSet);
        $fields = array('_id'=> true); // get the ids only

        return $this->connection->selectCollection($this->options['oid_table_name'])->find($query, $fields);
    }

    /**
     * Constructs the query used for looking up object identities and associated
     * ACEs, and security identities.
     *
     * @param array $batch
     * @param array $sids
     * @throws AclNotFoundException
     * @return string
     */
    protected function getLookupQuery(array $batch, array $sids)
    {
        // FIXME: add support for filtering by sids (right now we select all sids)

        $ancestors = $this->getAncestors($batch);
        if (0 === count($ancestors)) {
            throw new AclNotFoundException('There is no ACL for the given object identity.');
        }
    }
}