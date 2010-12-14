<?php

namespace Symfony\Component\Security\Acl\Model;

/**
 * Retrieves the object identity for a given domain object
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface ObjectIdentityRetrievalStrategyInterface
{
    /**
     * Retrievies the object identity from a domain object
     *
     * @param object $domainObject
     * @return ObjectIdentityInterface
     */
    function getObjectIdentity($domainObject);
}