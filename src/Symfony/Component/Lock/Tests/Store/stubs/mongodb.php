<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MongoDB;

use MongoDB\Driver\Manager;

/*
 * Stubs for the mongodb/mongodb library version ~1.16
 */
if (!class_exists(Client::class)) {
    abstract class Client
    {
        abstract public function getManager(): Manager;
    }
}

if (!class_exists(Database::class)) {
    abstract class Database
    {
        abstract public function getManager(): Manager;

        abstract public function getDatabaseName(): string;
    }
}

if (!class_exists(Collection::class)) {
    abstract class Collection
    {
        abstract public function getManager(): Manager;

        abstract public function getCollectionName(): string;

        abstract public function getDatabaseName(): string;
    }
}
