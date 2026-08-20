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
 * Stub for the mongodb/mongodb library, declared only when the real class
 * cannot be autoloaded, so that it never shadows the library for other tests
 * running in the same process. The autoload attempt is gated on the extension
 * being loaded, as the library classes cannot be loaded without it.
 */
if (!class_exists(Client::class, \extension_loaded('mongodb'))) {
    abstract class Client
    {
        abstract public function getManager(): Manager;
    }
}
