<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Acl\Dbal;

/**
 * TablesInterface.
 *
 * @author Daniel Oliveira <daniel@headdev.com.br>
 */
interface TablesInterface
{
    /**
     * Adds the class table to the schema.
     */
    public function addClassTable();

    /**
     * Adds the entry table to the schema.
     */
    public function addEntryTable();
    /**
     * Adds the object identity table to the schema.
     */
    public function addObjectIdentitiesTable();

    /**
     * Adds the object identity relation table to the schema.
     */
    public function addObjectIdentityAncestorsTable();

    /**
     * Adds the security identity table to the schema.
     */
    public function addSecurityIdentitiesTable();
}
