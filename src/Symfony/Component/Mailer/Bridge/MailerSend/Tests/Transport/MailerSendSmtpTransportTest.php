<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\MailerSend\Transport\MailerSendSmtpTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

class MailerSendSmtpTransportTest extends TestCase
{
    public function testTagHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('tag1'));
        $email->getHeaders()->add(new TagHeader('tag2'));

        $transport = new MailerSendSmtpTransport('USERNAME', 'PASSWORD');
        $method = new \ReflectionMethod(MailerSendSmtpTransport::class, 'addMailerSendHeaders');
        $method->invoke($transport, $email);

        $this->assertSame('X-MailerSend-Tags: tag1,tag2', $email->getHeaders()->get('X-MailerSend-Tags')->toString());
        $this->assertFalse($email->getHeaders()->has('X-Tag'));
    }

    public function testCustomHeaderIsKept()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');

        $transport = new MailerSendSmtpTransport('USERNAME', 'PASSWORD');
        $method = new \ReflectionMethod(MailerSendSmtpTransport::class, 'addMailerSendHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(1, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
    }

    public function testTagHeadersThrowsForTooManyTags()
    {
        $email = new Email();
        for ($i = 0; $i < 6; ++$i) {
            $email->getHeaders()->add(new TagHeader('tag'.$i));
        }

        $transport = new MailerSendSmtpTransport('USERNAME', 'PASSWORD');
        $method = new \ReflectionMethod(MailerSendSmtpTransport::class, 'addMailerSendHeaders');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Too many "Symfony\Component\Mailer\Header\TagHeader" instances present in the email headers. MailerSend does not accept more than 5 tags on an email.');
        $method->invoke($transport, $email);
    }
}
