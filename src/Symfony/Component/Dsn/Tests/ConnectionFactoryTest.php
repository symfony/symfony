<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Dsn\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Dsn\ConnectionFactory;

/**
 * @requires extension redis
 * @requires extension memcached
 */
class ConnectionFactoryTest extends TestCase
{
    /**
     * @dataProvider provideValidDsn
     */
    public function testGetType($dsn, $type)
    {
        $this->assertSame($type, ConnectionFactory::getType($dsn));
    }

    /**
     * @dataProvider provideInvalidDsn
     * @expectedException \Symfony\Component\Dsn\Exception\InvalidArgumentException
     */
    public function testGetTypeInvalid($dsn)
    {
        ConnectionFactory::getType($dsn);
    }

    /**
     * @dataProvider provideValidDsn
     */
    public function testCreate($dsn, $type, $objectClass)
    {
        $this->assertInstanceOf($objectClass, ConnectionFactory::create($dsn));
    }

    /**
     * @dataProvider provideInvalidDsn
     * @expectedException \Symfony\Component\Dsn\Exception\InvalidArgumentException
     */
    public function testCreateInvalid($dsn)
    {
        ConnectionFactory::create($dsn);
    }

    public function provideValidDsn()
    {
        yield array('redis://localhost', ConnectionFactory::TYPE_REDIS, \Redis::class);
        yield array('memcached://localhost', ConnectionFactory::TYPE_MEMCACHED, \Memcached::class);
    }

    public function provideInvalidDsn()
    {
        yield array(array('http://localhost'));
        yield array('http://localhost');
        yield array('mysql://localhost');
    }
}
