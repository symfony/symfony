<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport\Smtp\Stream;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class SocketStreamTest extends TestCase
{
    public function testSocketErrorNoConnection()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/Connection refused|unable to connect/i');
        $s = new SocketStream();
        $s->setTimeout(0.1);
        $s->setPort(9999);
        $s->initialize();
    }

    public function testSocketErrorBeforeConnectError()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/no valid certs found cafile stream|Unable to find the socket transport "ssl"/');
        $s = new SocketStream();
        $s->setStreamOptions([
            'ssl' => [
                // not a CA file :)
                'cafile' => __FILE__,
            ],
        ]);
        $s->setHost('smtp.gmail.com');
        $s->setPort(465);
        $s->initialize();
    }
}
