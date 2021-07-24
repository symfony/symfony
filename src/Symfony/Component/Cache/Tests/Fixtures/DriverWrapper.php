<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Fixtures;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

class DriverWrapper implements Driver
{
    /** @var Driver */
    private $driver;

    public function __construct(Driver $driver)
    {
        $this->driver = $driver;
    }

    public function connect(array $params, $username = null, $password = null, array $driverOptions = []): Driver\Connection
    {
        return $this->driver->connect($params, $username, $password, $driverOptions);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->driver->getDatabasePlatform();
    }

    public function getSchemaManager(Connection $conn, AbstractPlatform $platform): AbstractSchemaManager
    {
        return $this->driver->getSchemaManager($conn, $platform);
    }

    public function getExceptionConverter(): Driver\API\ExceptionConverter
    {
        return $this->driver->getExceptionConverter();
    }
}
