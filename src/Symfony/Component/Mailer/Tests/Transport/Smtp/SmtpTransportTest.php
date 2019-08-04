<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport\Smtp;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class SmtpTransportTest extends TestCase
{
    public function testName()
    {
        $t = new SmtpTransport();
        $this->assertEquals('smtp://localhost:25', $t->getName());

        $t = new SmtpTransport((new SocketStream())->setHost('127.0.0.1')->setPort(2525));
        $this->assertEquals('smtp://127.0.0.1:2525', $t->getName());
    }
}
