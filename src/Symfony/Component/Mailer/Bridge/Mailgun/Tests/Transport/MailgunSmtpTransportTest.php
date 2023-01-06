<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunSmtpTransport;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class MailgunSmtpTransportTest extends TestCase
{
    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));

        $transport = new MailgunSmtpTransport('user', 'password');
        $method = new \ReflectionMethod(MailgunSmtpTransport::class, 'addMailgunHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(3, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('foo')->toString());
        $this->assertSame('X-Mailgun-Tag: password-reset', $email->getHeaders()->get('X-Mailgun-Tag')->toString());
        $this->assertSame('X-Mailgun-Variables: '.json_encode(['Color' => 'blue', 'Client-ID' => '12345']), $email->getHeaders()->get('X-Mailgun-Variables')->toString());
    }
}
