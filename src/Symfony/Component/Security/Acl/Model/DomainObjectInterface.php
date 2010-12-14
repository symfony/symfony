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
 * This method can be implemented by domain objects which you want to store
 * ACLs for if they do not have a getId() method, or getId() does not return
 * a unique identifier.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface DomainObjectInterface
{
    /**
     * Returns a unique identifier for this domain object.
     *
     * @return string
     */
    function getObjectIdentifier();
}