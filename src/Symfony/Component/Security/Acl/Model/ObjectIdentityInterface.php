<?php

namespace Symfony\Component\Security\Acl\Model;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Represents the identity of an individual domain object instance.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface ObjectIdentityInterface
{
    /**
     * We specifically require this method so we can check for object equality
     * explicitly, and do not have to rely on referencial equality instead.
     *
     * Though in most cases, both checks should result in the same outcome.
     *
     * Referential Equality: $object1 === $object2
     * Example for Object Equality: $object1->getId() === $object2->getId()
     *
     * @param ObjectIdentityInterface $identity
     * @return Boolean
     */
    function equals(ObjectIdentityInterface $identity);

    /**
     * Obtains a unique identifier for this object. The identifier must not be
     * re-used for other objects with the same type.
     *
     * @return string cannot return null
     */
    function getIdentifier();

    /**
     * Returns a type for the domain object. Typically, this is the PHP class name.
     *
     * @return string cannot return null
     */
    function getType();
}