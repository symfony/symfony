<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Security\Acl;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Model\DomainObjectIdentityRetrievalStrategyInterface;
use Symfony\Component\Security\Acl\Model\DomainObjectInterface;

use Doctrine\ORM\Proxy\Proxy;

/**
 * The Doctrine implementation for DomainObjectIdentityRetrievalStrategyInterface
 *
 * This class is aware of Doctrine proxies and entities real identifiers
 *
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 */
class DomainObjectIdentityRetrievalStrategy implements DomainObjectIdentityRetrievalStrategyInterface
{
    private $registry;

    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * {@inheritDoc}
     */
    public function getDomainObjectIdentity($domainObject)
    {
        // Check if this object is managed by Doctrine ORM
        if (null === $em = $this->registry->getEntityManagerForClass($domainObject)) {
            return null;
        }

        // We want the user to be able to override the identifier
        if ($domainObject instanceof DomainObjectInterface) {
            return new ObjectIdentity((string) $domainObject->getObjectIdentifier(), $this->getEntityClass($domainObject));
        }

        // We don't want to load the object
        $ids = $em->getUnitOfWork()->getEntityIdentifier($domainObject);
        $identifier = (1 === count($ids)) ? (string) $ids[0] : serialize($ids);

        return new ObjectIdentity($identifier, $this->getEntityClass($domainObject));
    }

    /**
    * {@inheritDoc}
    */
    public function getDomainUserSecurityIdentityFromAccount(UserInterface $user)
    {
        // Check if this object is managed by Doctrine ORM
        if (null === $this->registry->getEntityManagerForClass($user)) {
            return null;
        }

        return new UserSecurityIdentity($user->getUsername(), $this->getEntityClass($user));
    }

    /**
    * {@inheritDoc}
    */
    public function getDomainUserSecurityIdentityFromToken(TokenInterface $token)
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return null;
        }

        return $this->getDomainUserSecurityIdentityFromAccount($user);
    }

    /**
     * Returns the real class of this domain object.
     * Never returns a proxy class.
     *
     * @param object $object
     */
    private function getEntityClass($object)
    {
        if ($object instanceof Proxy) {
            return get_parent_class($object);
        }

        return get_class($object);
    }
}
