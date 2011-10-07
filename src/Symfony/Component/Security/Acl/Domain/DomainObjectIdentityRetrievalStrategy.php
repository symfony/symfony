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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Model\DomainObjectIdentityRetrievalStrategyInterface;

/**
 * The default implementation for DomainObjectIdentityRetrievalStrategyInterface
 *
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 */
class DomainObjectIdentityRetrievalStrategy implements DomainObjectIdentityRetrievalStrategyInterface
{
    private $identityResolvers;

    /**
     * Constructor
     *
     * @param array $identityResolvers
     */
    public function __construct(array $identityResolvers = array())
    {
        $this->identityResolvers = $identityResolvers;

        foreach ($this->identityResolvers as $identityResolver) {
            if (!$identityResolver instanceof DomainObjectIdentityRetrievalStrategyInterface) {
                throw new \InvalidArgumentException('Identity resolvers must implement Symfony\Component\Security\Acl\Model\DomainObjectIdentityRetrievalStrategyInterface');
            }
        }
    }

    /**
     * Loops over all registered identity resolvers and fall
     * back to the default implementation if none supports this
     * domain object
     *
     * {@inheritDoc}
     */
    public function getDomainObjectIdentity($domainObject)
    {
        foreach ($this->identityResolvers as $identityResolver) {
            if (null !== $objectIdentity = $identityResolver->getDomainObjectIdentity($domainObject)) {
                return $objectIdentity;
            }
        }

        return ObjectIdentity::fromDomainObject($domainObject);
    }

    /**
     * Loops over all registered identity resolvers and fall
     * back to the default implementation if none supports this
     * user
     *
     * {@inheritDoc}
     */
    public function getDomainUserSecurityIdentityFromAccount(UserInterface $user)
    {
        foreach ($this->identityResolvers as $identityResolver) {
            if (null !== $securityIdentity = $identityResolver->getDomainUserSecurityIdentityFromAccount($user)) {
                return $securityIdentity;
            }
        }

        return UserSecurityIdentity::fromAccount($user);
    }

    /**
     * Loops over all registered identity resolvers and fall
     * back to the default implementation if none supports this
     * token
     *
     * {@inheritDoc}
     */
    public function getDomainUserSecurityIdentityFromToken(TokenInterface $token)
    {
        foreach ($this->identityResolvers as $identityResolver) {
            if (null !== $securityIdentity = $identityResolver->getDomainUserSecurityIdentityFromToken($token)) {
                return $securityIdentity;
            }
        }

        return UserSecurityIdentity::fromToken($token);
    }
}
