<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Groundmail\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Groundmail\Transport\GroundmailSmtpTransport;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Email;

class GroundmailSmtpTransportTest extends TestCase
{
    public function testToString()
    {
        $this->assertSame('smtp://smtp.groundmail.dev:587', (string) new GroundmailSmtpTransport('groundmail', 'PASSWORD'));
        $this->assertSame('smtp://smtp.groundmail.dev:2525', (string) new GroundmailSmtpTransport('groundmail', 'PASSWORD', 2525));
    }

    public function testCredentialsAndTls()
    {
        $transport = new GroundmailSmtpTransport('groundmail', 'gmsmtp_secret');

        $this->assertSame('groundmail', $transport->getUsername());
        $this->assertSame('gmsmtp_secret', $transport->getPassword());
        $this->assertTrue($transport->isTlsRequired());
        $this->assertFalse($transport->getStream()->isTLS());
    }

    public function testMetadataIsSentAsGroundmailTags()
    {
        $email = (new Email())
            ->from('fabpot@symfony.com')
            ->to('kevin@symfony.com')
            ->text('Hello');
        $email->getHeaders()
            ->add(new MetadataHeader('order_id', 'ord_123'))
            ->add(new MetadataHeader('user_id', 'u_42'))
            ->addTextHeader('X-Groundmail-Category', 'product');

        $headers = $email->getHeaders();
        $method = new \ReflectionMethod(GroundmailSmtpTransport::class, 'addTagHeaders');
        $method->invoke(new GroundmailSmtpTransport('groundmail', 'PASSWORD'), $headers);

        $this->assertSame(['order_id=ord_123', 'user_id=u_42'], array_map(static fn ($header) => $header->getBodyAsString(), iterator_to_array($headers->all('X-Groundmail-Tag'), false)));
        $this->assertFalse($headers->has('X-Metadata-order_id'));
        $this->assertFalse($headers->has('X-Metadata-user_id'));
        $this->assertSame('product', $headers->get('X-Groundmail-Category')->getBodyAsString());
    }
}
