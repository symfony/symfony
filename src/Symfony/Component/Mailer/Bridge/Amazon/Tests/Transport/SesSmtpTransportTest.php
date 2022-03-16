<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Amazon\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesSmtpTransport;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Email;

class SesSmtpTransportTest extends TestCase
{
    public function testTagAndMetadataAndMessageStreamHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new MetadataHeader('tagName1', 'tag Value1'));
        $email->getHeaders()->add(new MetadataHeader('tagName2', 'tag Value2'));

        $transport = new SesSmtpTransport('user', 'pass');
        $method = new \ReflectionMethod(SesSmtpTransport::class, 'addSesHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(1, $email->getHeaders()->toArray());
        $this->assertTrue($email->getHeaders()->has('X-SES-MESSAGE-TAGS'));
        $this->assertSame('X-SES-MESSAGE-TAGS: tagName1=tag Value1, tagName2=tag Value2', $email->getHeaders()->get('X-SES-MESSAGE-TAGS')->toString());
    }
}
