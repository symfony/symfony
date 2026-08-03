<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\PdoAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;

class AbstractAdapterCreateAdapterTest extends TestCase
{
    #[RequiresPhpExtension('redis')]
    public function testCreateAdapterFromRedisConnection()
    {
        $connection = AbstractAdapter::createConnection('redis://localhost', ['lazy' => true]);

        $this->assertInstanceOf(RedisAdapter::class, AbstractAdapter::createAdapter($connection));
    }

    #[RequiresPhpExtension('memcached')]
    public function testCreateAdapterFromMemcachedConnection()
    {
        $connection = AbstractAdapter::createConnection('memcached://localhost');

        $this->assertInstanceOf(MemcachedAdapter::class, AbstractAdapter::createAdapter($connection));
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testCreateAdapterFromPdoConnection()
    {
        $connection = AbstractAdapter::createConnection('sqlite::memory:');

        $this->assertInstanceOf(PdoAdapter::class, AbstractAdapter::createAdapter($connection));
    }

    public function testCreateAdapterPassesTheMarshallerThrough()
    {
        $adapter = AbstractAdapter::createAdapter('sqlite::memory:', 'ns', 0, new DefaultMarshaller());

        $this->assertInstanceOf(PdoAdapter::class, $adapter);
    }

    public function testCreateAdapterThrowsOnAStringThatIsNotAPdoDsn()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported connection: "string".');

        AbstractAdapter::createAdapter('redis://localhost');
    }

    public function testCreateAdapterThrowsOnUnsupportedConnection()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported connection: "stdClass".');

        AbstractAdapter::createAdapter(new \stdClass());
    }
}
