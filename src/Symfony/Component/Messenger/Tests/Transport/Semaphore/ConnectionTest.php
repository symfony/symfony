<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Semaphore;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Semaphore\Connection;

class ConnectionTest extends TestCase
{
    public function testItCannotBeConstructedWithAWrongDsn()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The given Semaphore Messenger DSN "semaphore://:" is invalid.');
        Connection::fromDsn('semaphore://:');
    }

    public function testItCanBeConstructedWithDefaults()
    {
        $this->assertEquals(
                new Connection([
                    'path' => '/',
                    'project' => 'M',
                    'message_max_size' => 131072,
                ]),
                Connection::fromDsn('semaphore:///')
        );
    }

    public function testOverrideOptionsViaQueryParameters()
    {
        $this->assertEquals(
                new Connection([
                    'path' => '/.env',
                    'project' => 'T',
                    'message_max_size' => 1024,
                ]),
                Connection::fromDsn('semaphore:///.env?project=T&message_max_size=1024')
        );
    }

    public function testOptionsAreTakenIntoAccountAndOverwrittenByDsn()
    {
        $this->assertEquals(
                new Connection([
                    'path' => '/.env',
                    'project' => 'T',
                    'message_max_size' => 1024,
                ]),
                Connection::fromDsn('semaphore:///.env?project=T&message_max_size=1024', [
                    'message_max_size' => 131072,
                ])
        );
    }
}
