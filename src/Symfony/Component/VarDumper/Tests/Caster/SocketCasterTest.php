<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires extension sockets
 */
class SocketCasterTest extends TestCase
{
    use VarDumperTestTrait;

    /**
     * @requires PHP 8.3
     */
    public function testCastSocket()
    {
        $socket = socket_create(\AF_INET, \SOCK_DGRAM, \SOL_UDP);
        @socket_connect($socket, '127.0.0.1', 80);

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Socket {
  uri: "udp://127.0.0.1:%d"
  timed_out: false
  blocked: true%A
}
EODUMP, $socket);
    }

    /**
     * @requires PHP < 8.3
     */
    public function testCastSocketPriorToPhp83()
    {
        $socket = socket_create(\AF_INET, \SOCK_DGRAM, \SOL_UDP);
        @socket_connect($socket, '127.0.0.1', 80);

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Socket {
  timed_out: false
  blocked: true
}
EODUMP, $socket);
    }

    /**
     * @requires PHP 8.3
     */
    public function testCastSocketIpV6()
    {
        $socket = socket_create(\AF_INET6, \SOCK_STREAM, \SOL_TCP);
        @socket_connect($socket, '::1', 80);

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Socket {
  uri: "tcp://[%A]:%d"
  timed_out: false
  blocked: true
  last_error: SOCKET_ECONNREFUSED
}
EODUMP, $socket);
    }

    /**
     * @requires PHP < 8.3
     */
    public function testCastSocketIpV6PriorToPhp83()
    {
        $socket = socket_create(\AF_INET6, \SOCK_STREAM, \SOL_TCP);
        @socket_connect($socket, '::1', 80);

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Socket {
  timed_out: false
  blocked: true
  last_error: SOCKET_ECONNREFUSED
}
EODUMP, $socket);
    }

    /**
     * @requires PHP 8.3
     */
    public function testCastUnixSocket()
    {
        $socket = socket_create(\AF_UNIX, \SOCK_STREAM, 0);
        @socket_connect($socket, '/tmp/socket.sock');

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Socket {
  uri: "unix://"
  timed_out: false
  blocked: true
  last_error: SOCKET_ENOENT
}
EODUMP, $socket);
    }

    /**
     * @requires PHP < 8.3
     */
    public function testCastUnixSocketPriorToPhp83()
    {
        $socket = socket_create(\AF_UNIX, \SOCK_STREAM, 0);
        @socket_connect($socket, '/tmp/socket.sock');

        $this->assertDumpMatchesFormat(
            <<<'EODUMP'
Socket {
  timed_out: false
  blocked: true
  last_error: SOCKET_ENOENT
}
EODUMP, $socket);
    }
}
