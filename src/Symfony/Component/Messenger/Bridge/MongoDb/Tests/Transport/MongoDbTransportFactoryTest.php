<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Tests\Transport;

require_once __DIR__.'/../Stubs/mongodb.php';

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbTransport;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbTransportFactory;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MongoDbTransportFactoryTest extends TestCase
{
    public function testSupports()
    {
        $factory = new MongoDbTransportFactory();

        $this->assertTrue($factory->supports('mongodb://localhost:27017/db', []));
        $this->assertTrue($factory->supports('mongodb+srv://cluster.example.com/db', []));
        $this->assertFalse($factory->supports('doctrine://default', []));
        $this->assertFalse($factory->supports('redis://localhost', []));
    }

    public function testCreateTransport()
    {
        $factory = new MongoDbTransportFactory();
        $transport = $factory->createTransport('mongodb://localhost:27017/db', ['transport_name' => 'mongo'], $this->createStub(SerializerInterface::class));

        $this->assertInstanceOf(MongoDbTransport::class, $transport);
    }
}
