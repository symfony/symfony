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
            'foo' => $foo = self::createMock(TransportInterface::class),
            'bar' => $bar = self::createMock(TransportInterface::class),
        ]);

        $foo->expects(self::once())->method('send');
        $bar->expects(self::never())->method('send');

        $email = new Message(new Headers(), new TextPart('...'));
        $transport->send($email);
    }

    public function testOverrideTransport()
    {
        $transport = new Transports([
            'foo' => $foo = self::createMock(TransportInterface::class),
            'bar' => $bar = self::createMock(TransportInterface::class),
        ]);

        $foo->expects(self::never())->method('send');
        $bar->expects(self::once())->method('send');

        $headers = (new Headers())->addTextHeader('X-Transport', 'bar');
        $email = new Message($headers, new TextPart('...'));
        $transport->send($email);
    }

    public function testTransportDoesNotExist()
    {
        $transport = new Transports([
            'foo' => self::createMock(TransportInterface::class),
            'bar' => self::createMock(TransportInterface::class),
        ]);

        $headers = (new Headers())->addTextHeader('X-Transport', 'foobar');
        $email = new Message($headers, new TextPart('...'));

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('The "foobar" transport does not exist (available transports: "foo", "bar").');
        $transport->send($email);
    }

    public function testTransportRestoredAfterFailure()
    {
        $exception = new \Exception();

        $fooTransport = self::createMock(TransportInterface::class);
        $fooTransport->method('send')
            ->willThrowException($exception);

        $transport = new Transports([
            'foo' => $fooTransport,
        ]);

        $headers = (new Headers())->addTextHeader('X-Transport', 'foo');
        $email = new Message($headers, new TextPart('...'));

        self::expectExceptionObject($exception);

        try {
            $transport->send($email);
        } finally {
            self::assertSame('foo', $email->getHeaders()->getHeaderBody('X-Transport'));
        }
    }
}
