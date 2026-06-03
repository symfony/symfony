<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailtrap\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapSmtpTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

class MailtrapSmtpTransportTest extends TestCase
{
    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');

        $transport = new MailtrapSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailtrapSmtpTransport::class, 'addMailtrapHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(1, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
    }

    public function testTagAndMetadata()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));

        $transport = new MailtrapSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailtrapSmtpTransport::class, 'addMailtrapHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(3, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
        $this->assertSame('X-MT-Category: password-reset', $email->getHeaders()->get('X-MT-Category')->toString());
        $this->assertSame('X-MT-Custom-Variables: {"Color":"blue","Client-ID":"12345"}', $email->getHeaders()->get('X-MT-Custom-Variables')->toString());
    }

    public function testMultipleTagsAreNotAllowed()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('tag1'));
        $email->getHeaders()->add(new TagHeader('tag2'));

        $transport = new MailtrapSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailtrapSmtpTransport::class, 'addMailtrapHeaders');

        $this->expectException(TransportException::class);

        $method->invoke($transport, $email);
    }
}
