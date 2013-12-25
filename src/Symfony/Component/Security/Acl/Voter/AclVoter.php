<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Voter;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Acl\Exception\NoAceFoundException;
use Symfony\Component\Security\Acl\Exception\AclNotFoundException;
use Symfony\Component\Security\Acl\Model\AclProviderInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;
use Symfony\Component\Security\Acl\Permission\PermissionMapInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityRetrievalStrategyInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityRetrievalStrategyInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * This voter can be used as a base class for implementing your own permissions.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AclVoter implements VoterInterface
{
    private $aclProvider;
    private $permissionMap;
    private $objectIdentityRetrievalStrategy;
    private $securityIdentityRetrievalStrategy;
    private $allowIfObjectIdentityUnavailable;
    private $logger;

    /**
     * @param AclProviderInterface                       $aclProvider
     * @param ObjectIdentityRetrievalStrategyInterface   $oidRetrievalStrategy
     * @param SecurityIdentityRetrievalStrategyInterface $sidRetrievalStrategy
     * @param PermissionMapInterface                     $permissionMap
     * @param LoggerInterface|null                       $logger
     * @param Boolean                                    $allowIfObjectIdentityUnavailable
     */
    public function __construct(AclProviderInterface $aclProvider, ObjectIdentityRetrievalStrategyInterface $oidRetrievalStrategy, SecurityIdentityRetrievalStrategyInterface $sidRetrievalStrategy, PermissionMapInterface $permissionMap, LoggerInterface $logger = null, $allowIfObjectIdentityUnavailable = true)
    {
        $this->aclProvider = $aclProvider;
        $this->permissionMap = $permissionMap;
        $this->objectIdentityRetrievalStrategy = $oidRetrievalStrategy;
        $this->securityIdentityRetrievalStrategy = $sidRetrievalStrategy;
        $this->logger = $logger;
        $this->allowIfObjectIdentityUnavailable = $allowIfObjectIdentityUnavailable;
    }

    public function supportsAttribute($attribute)
    {
        return $this->permissionMap->contains($attribute);
    }

    public function vote(TokenInterface $token, $object, array $attributes)
    {
        if (null === $attributesMasks = $this->getAttributesMasks($attributes, $object)) {
            return self::ACCESS_ABSTAIN;
        }

        if (null === $object) {
            return $this->voteObjectIdentityUnavailable();
        }

        if ($object instanceof FieldVote) {
            $field = $object->getField();
            $object = $object->getDomainObject();
        } else {
            $field = null;
        }

        if ($object instanceof ObjectIdentityInterface) {
            $oid = $object;
        } elseif (null === $oid = $this->objectIdentityRetrievalStrategy->getObjectIdentity($object)) {
            return $this->voteObjectIdentityUnavailable();
        }

        if (!$this->supportsClass($oid->getType())) {
            return self::ACCESS_ABSTAIN;
        }

        $sids = $this->securityIdentityRetrievalStrategy->getSecurityIdentities($token);

        foreach ($attributesMasks as $masks) {
            try {
                $acl = $this->aclProvider->findAcl($oid, $sids);

                if (null === $field && $acl->isGranted($masks, $sids, false)) {
                    $this->logDebug('ACL found, permission granted. Voting to grant access');

                    return self::ACCESS_GRANTED;
                }

                if (null !== $field && $acl->isFieldGranted($field, $masks, $sids, false)) {
                    $this->logDebug('ACL found, permission granted. Voting to grant access');

                    return self::ACCESS_GRANTED;
                }

                $this->logDebug('ACL found, insufficient permissions. Voting to deny access.');

                return self::ACCESS_DENIED;
            } catch (AclNotFoundException $noAcl) {
                $this->logDebug('No ACL found for the object identity. Voting to deny access.');

                return self::ACCESS_DENIED;
            } catch (NoAceFoundException $noAce) {
                $this->logDebug('ACL found, no ACE applicable. Voting to deny access.');

                return self::ACCESS_DENIED;
            }
        }

        // no attribute was supported
        return self::ACCESS_ABSTAIN;
    }

    /**
     * You can override this method when writing a voter for a specific domain
     * class.
     *
     * @param string $class The class name
     *
     * @return Boolean
     */
    public function supportsClass($class)
    {
        return true;
    }

    /**
     * Log debug message if logger defined
     *
     * @param string $message
     * @param array  $context
     */
    private function logDebug($message, array $context = array())
    {
        if (null !== $this->logger) {
            $this->logger->debug($message, $context);
        }
    }

    /**
     * Get vote for object with no identity
     *
     * @return integer
     */
    private function voteObjectIdentityUnavailable()
    {
        $this->logDebug(sprintf('Object identity unavailable. Voting to %s', $this->allowIfObjectIdentityUnavailable ? 'grant access' : 'abstain'));

        return $this->allowIfObjectIdentityUnavailable ? self::ACCESS_GRANTED : self::ACCESS_ABSTAIN;
    }

    /**
     * Retrieve array containing the masks for all attributes
     *
     * @param array $attributes
     * @param       $object
     *
     * @return array|null
     */
    private function getAttributesMasks(array $attributes, $object)
    {
        $attributesMasks = null;

        foreach ($attributes as $attribute) {
            if (null !== $masks = $this->permissionMap->getMasks($attribute, $object)) {
                $attributesMasks[] = $masks;
            }
        }

        return $attributesMasks;
    }
}
