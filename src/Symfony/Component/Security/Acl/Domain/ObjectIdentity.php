<?php

namespace Symfony\Component\Security\Acl\Domain;

use Symfony\Component\Security\Acl\Exception\InvalidDomainObjectException;
use Symfony\Component\Security\Acl\Model\DomainObjectInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ObjectIdentity implementation
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ObjectIdentity implements ObjectIdentityInterface
{
    protected $identifier;
    protected $type;

    /**
     * Constructor
     *
     * @param string $identifier
     * @param string $type
     * @return void
     */
    public function __construct($identifier, $type)
    {
        if (empty($identifier)) {
            throw new \InvalidArgumentException('$identifier cannot be empty.');
        }
        if (empty($type)) {
            throw new \InvalidArgumentException('$type cannot be empty.');
        }

        $this->identifier = $identifier;
        $this->type = $type;
    }

    /**
     * Constructs an ObjectIdentity for the given domain object
     *
     * @param object $domainObject
     * @throws \InvalidArgumentException
     * @return ObjectIdentity
     */
    public static function fromDomainObject($domainObject)
    {
        if (!is_object($domainObject)) {
            throw new InvalidDomainObjectException('$domainObject must be an object.');
        }

        if ($domainObject instanceof DomainObjectInterface) {
            return new self($domainObject->getObjectIdentifier(), get_class($domainObject));
        } else if (method_exists($domainObject, 'getId')) {
            return new self($domainObject->getId(), get_class($domainObject));
        }

        throw new InvalidDomainObjectException('$domainObject must either implement the DomainObjectInterface, or have a method named "getId".');
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * {@inheritDoc}
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * {@inheritDoc}
     */
    public function equals(ObjectIdentityInterface $identity)
    {
        // comparing the identifier with === might lead to problems, so we
        // waive this restriction
        return $this->identifier == $identity->getIdentifier()
               && $this->type === $identity->getType();
    }

    /**
     * Returns a textual representation of this object identity
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('ObjectIdentity(%s, %s)', $this->identifier, $this->type);
    }
}