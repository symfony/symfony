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
if (!class_exists(Client::class, false)) {
    abstract class Client
    {
        abstract public function getManager(): Manager;
    }
}
