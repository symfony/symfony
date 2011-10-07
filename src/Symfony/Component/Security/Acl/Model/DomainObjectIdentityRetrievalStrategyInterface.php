<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Model;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * This interface can be implemented by services able to retrieve a ObjectIdentityInterface
 * or a UserSecurityIdentityInterface for particular objects.
 *
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 */
interface DomainObjectIdentityRetrievalStrategyInterface
{
    /**
     * Returns the ObjectIdentityInterface for this domain object
     * or null if the object is not supported.
     *
     * @param object $domainObject
     * @return ObjectIdentityInterface|null
     */
    function getDomainObjectIdentity($domainObject);

    /**
     * Returns the SecurityIdentityInterface for this domain user account
     * or null if the object is not supported.
     *
     * @param UserInterface $user
     * @return SecurityIdentityInterface|null
     */
    function getDomainUserSecurityIdentityFromAccount(UserInterface $user);

    /**
     * Returns the SecurityIdentityInterface for this domain user token
     * or null if the object is not supported.
     *
     * @param TokenInterface $token
     * @return SecurityIdentityInterface|null
     */
    function getDomainUserSecurityIdentityFromToken(TokenInterface $token);
}
