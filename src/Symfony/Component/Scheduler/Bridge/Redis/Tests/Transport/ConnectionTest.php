<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Redis\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Scheduler\Bridge\Redis\Transport\Connection;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Transport\Dsn;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 *
 * @requires extension redis >= 4.3.0
 */
final class ConnectionTest extends TestCase
{
    use ExpectDeprecationTrait;

    public static function setUpBeforeClass(): void
    {
        $redis = Connection::createFromDsn(Dsn::fromString('redis://localhost/tasks'));

        try {
            $redis->get('test');
        } catch (TransportException $e) {
            if (0 === strpos($e->getMessage(), 'ERR unknown command \'X')) {
                self::markTestSkipped('Redis server >= 5 is required');
            }

            throw $e;
        }
    }

    public function testFromDsnWithOptions()
    {
        static::assertEquals(
            Connection::createFromDsn(new Dsn('redis://', 'localhost', 'root', 'test', 6379, ['dbindex' => 'test'])),
            Connection::createFromDsn(Dsn::fromString('redis://root:test?host=localhost&port=6379&dbindex=test'))
        );
    }
}
