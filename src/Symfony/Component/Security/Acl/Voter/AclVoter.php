<?php

namespace Symfony\Component\Security\Acl\Voter;

use Symfony\Component\Security\Acl\Domain\ObjectIdentity;
use Symfony\Component\Security\Acl\Domain\RoleSecurityIdentity;
use Symfony\Component\Security\Acl\Domain\UserSecurityIdentity;
use Symfony\Component\Security\Acl\Exception\NoAceFoundException;
use Symfony\Component\Security\Acl\Exception\AclNotFoundException;
use Symfony\Component\Security\Acl\Model\AclProviderInterface;
use Symfony\Component\Security\Acl\Permission\PermissionMapInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityRetrievalStrategyInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityRetrievalStrategyInterface;
use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Role\RoleHierarchyInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * This voter can be used as a base class for implementing your own permissions.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AclVoter implements VoterInterface
{
    protected $aclProvider;
    protected $permissionMap;
    protected $objectIdentityRetrievalStrategy;
    protected $securityIdentityRetrievalStrategy;

    public function __construct(AclProviderInterface $aclProvider, ObjectIdentityRetrievalStrategyInterface $oidRetrievalStrategy, SecurityIdentityRetrievalStrategyInterface $sidRetrievalStrategy, PermissionMapInterface $permissionMap)
    {
        $this->aclProvider = $aclProvider;
        $this->permissionMap = $permissionMap;
        $this->objectIdentityRetrievalStrategy = $oidRetrievalStrategy;
        $this->securityIdentityRetrievalStrategy = $sidRetrievalStrategy;
    }

    public function supportsAttribute($attribute)
    {
        return $this->permissionMap->contains($attribute);
    }

    public function vote(TokenInterface $token, $object, array $attributes)
    {
        if (null === $object) {
            return self::ACCESS_ABSTAIN;
        } else if ($object instanceof FieldVote) {
            $field = $object->getField();
            $object = $object->getDomainObject();
        } else {
            $field = null;
        }

        if (null === $oid = $this->objectIdentityRetrievalStrategy->getObjectIdentity($object)) {
            return self::ACCESS_ABSTAIN;
        }
        $sids = $this->securityIdentityRetrievalStrategy->getSecurityIdentities($token);

        foreach ($attributes as $attribute) {
            if (!$this->supportsAttribute($attribute)) {
                continue;
            }

            try {
                $acl = $this->aclProvider->findAcl($oid, $sids);
            } catch (AclNotFoundException $noAcl) {
                return self::ACCESS_DENIED;
            }

            try {
                if (null === $field && $acl->isGranted($this->permissionMap->getMasks($attribute), $sids, false)) {
                    return self::ACCESS_GRANTED;
                } else if (null !== $field && $acl->isFieldGranted($field, $this->permissionMap->getMasks($attribute), $sids, false)) {
                    return self::ACCESS_GRANTED;
                } else {
                    return self::ACCESS_DENIED;
                }
            } catch (NoAceFoundException $noAce) {
                return self::ACCESS_DENIED;
            }
        }

        return self::ACCESS_ABSTAIN;
    }

    /**
     * You can override this method when writing a voter for a specific domain
     * class.
     *
     * @return Boolean
     */
    public function supportsClass($class)
    {
        return true;
    }
}