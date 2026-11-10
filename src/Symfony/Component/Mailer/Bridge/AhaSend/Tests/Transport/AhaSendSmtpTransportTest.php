<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\AhaSend\Transport\AhaSendSmtpTransport;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Symfony\Component\Mime\Email;

class AhaSendSmtpTransportTest extends TestCase
{
    public function testTheMessageIdIsTheMessageIdHeaderWithoutAngleBrackets()
    {
        $stream = new class extends AbstractStream {
            private string $reply;

            public function initialize(): void
            {
                $this->reply = '220 send.ahasend.com ESMTP';
            }

            public function write(string $bytes, bool $debug = true): void
            {
                $this->reply = match (true) {
                    str_starts_with($bytes, 'DATA') => '354 Go ahead',
                    "\r\n.\r\n" === $bytes => '250 OK ids=d7ef132b5d7711eea8c8000c29c33806',
                    default => '250 OK',
                };
            }

            public function readLine(): string
            {
                return $this->reply."\r\n";
            }

            public function flush(): void
            {
            }

            public function isTLS(): bool
            {
                return false;
            }

            protected function getReadConnectionDescription(): string
            {
                return 'send.ahasend.com';
            }
        };
        $transport = new AhaSendSmtpTransport('USERNAME', 'PASSWORD');
        (new \ReflectionProperty(SmtpTransport::class, 'stream'))->setValue($transport, $stream);

        $sentMessage = $transport->send((new Email())->from('foo@example.com')->to('bar@example.com')->text('Test email body'));

        $this->assertSame(1, preg_match('/^Message-ID: <([^>]++)>\r$/m', $sentMessage->toString(), $matches));
        $this->assertSame($matches[1], $sentMessage->getMessageId());
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');

        $transport = new AhaSendSmtpTransport('USERNAME', 'PASSWORD');
        $method = new \ReflectionMethod(AhaSendSmtpTransport::class, 'addAhaSendHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(1, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
    }

    public function testMultipleTags()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('tag1'));
        $email->getHeaders()->add(new TagHeader('tag2'));

        $transport = new AhaSendSmtpTransport('USERNAME', 'PASSWORD');
        $method = new \ReflectionMethod(AhaSendSmtpTransport::class, 'addAhaSendHeaders');

        $method->invoke($transport, $email);
        $headers = $email->getHeaders();
        $this->assertSame('AhaSend-Tags: tag1,tag2', $email->getHeaders()->get('AhaSend-Tags')->toString());
    }
}
