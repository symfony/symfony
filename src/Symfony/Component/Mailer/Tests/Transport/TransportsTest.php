<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mailer\Transport\Transports;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\TextPart;

class TransportsTest extends TestCase
{
    public function testDefaultTransport()
    {
        $transport = new Transports([
            'foo' => $foo = $this->createMock(TransportInterface::class),
            'bar' => $bar = $this->createMock(TransportInterface::class),
        ]);

        $foo->expects($this->once())->method('send');
        $bar->expects($this->never())->method('send');

        $email = new Message(new Headers(), new TextPart('...'));
        $transport->send($email);
    }

    public function testOverrideTransport()
    {
        $transport = new Transports([
            'foo' => $foo = $this->createMock(TransportInterface::class),
            'bar' => $bar = $this->createMock(TransportInterface::class),
        ]);

        $foo->expects($this->never())->method('send');
        $bar->expects($this->once())->method('send');

        $headers = (new Headers())->addTextHeader('X-Transport', 'bar');
        $email = new Message($headers, new TextPart('...'));
        $transport->send($email);
    }

    public function testTransportDoesNotExist()
    {
        $transport = new Transports([
            'foo' => $this->createMock(TransportInterface::class),
            'bar' => $this->createMock(TransportInterface::class),
        ]);

        $headers = (new Headers())->addTextHeader('X-Transport', 'foobar');
        $email = new Message($headers, new TextPart('...'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "foobar" transport does not exist (available transports: "foo", "bar").');
        $transport->send($email);
    }

    public function testTransportRestoredAfterFailure()
    {
        $exception = new \Exception();

        $fooTransport = $this->createMock(TransportInterface::class);
        $fooTransport->method('send')
            ->willThrowException($exception);

        $transport = new Transports([
            'foo' => $fooTransport,
        ]);

        $headers = (new Headers())->addTextHeader('X-Transport', 'foo');
        $email = new Message($headers, new TextPart('...'));

        $this->expectExceptionObject($exception);

        try {
            $transport->send($email);
        } finally {
            $this->assertSame('foo', $email->getHeaders()->getHeaderBody('X-Transport'));
        }
    }
}
