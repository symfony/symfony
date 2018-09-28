<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\RedisExt;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\RedisExt\Connection;

/**
 * @requires extension redis
 */
class ConnectionTest extends TestCase
{
    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage The given Redis DSN "redis://" is invalid.
     */
    public function testItCannotBeConstructedWithAWrongDsn()
    {
        Connection::fromDsn('redis://');
    }

    public function testItGetsParametersFromTheDsn()
    {
        $this->assertEquals(
            new Connection('queue', array(
                'host' => 'localhost',
                'port' => 6379,
            )),
            Connection::fromDsn('redis://localhost/queue')
        );
    }

    public function testOverrideOptionsViaQueryParameters()
    {
        $this->assertEquals(
            new Connection('queue', array(
                'host' => '127.0.0.1',
                'port' => 6379,
            ), array(
                'processing_ttl' => '8000',
            )),
            Connection::fromDsn('redis://127.0.0.1:6379/queue?processing_ttl=8000')
        );
    }
}
