<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Exception\InvalidDomainObjectException;
use Symfony\Component\Security\Acl\Model\DomainObjectIdentityRetrievalStrategyInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityRetrievalStrategyInterface;

/**
 * Strategy to be used for retrieving object identities from domain objects
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 */
class ObjectIdentityRetrievalStrategy implements ObjectIdentityRetrievalStrategyInterface
{
    private $identityResolver;

    /**
    * Constructor
    *
    * @param DomainObjectIdentityRetrievalStrategyInterface $identityResolver
    */
    public function __construct(DomainObjectIdentityRetrievalStrategyInterface $identityResolver)
    {
        $this->identityResolver = $identityResolver;
    }

    /**
     * {@inheritDoc}
     */
    public function getObjectIdentity($domainObject)
    {
        try {
            return $this->identityResolver->getDomainObjectIdentity($domainObject);
        } catch (InvalidDomainObjectException $failed) {
            return null;
        }
    }
}
