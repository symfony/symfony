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
use Symfony\Component\Mime\Email;

class AhaSendSmtpTransportTest extends TestCase
{
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
