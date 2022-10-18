<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\OhMySmtp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\OhMySmtp\Transport\OhMySmtpSmtpTransport;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

/**
 * @group legacy
 */
final class OhMySmtpSmtpTransportTest extends TestCase
{
    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');

        $transport = new OhMySmtpSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(OhMySmtpSmtpTransport::class, 'addOhMySmtpHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(1, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
    }

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new TagHeader('2nd-tag'));

        $transport = new OhMySmtpSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(OhMySmtpSmtpTransport::class, 'addOhMySmtpHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(2, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
        $this->assertSame('X-OMS-Tags: password-reset, 2nd-tag', $email->getHeaders()->get('X-OMS-Tags')->toString());
    }
}
