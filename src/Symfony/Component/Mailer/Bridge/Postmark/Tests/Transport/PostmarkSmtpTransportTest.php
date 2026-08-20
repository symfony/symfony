<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\MessageStreamHeader;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkSmtpTransport;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Email;

class PostmarkSmtpTransportTest extends TestCase
{
    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');

        $transport = new PostmarkSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkSmtpTransport::class, 'addPostmarkHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(2, $email->getHeaders()->toArray());
        $this->assertSame('X-PM-KeepID: true', $email->getHeaders()->get('X-PM-KeepID')->toString());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
    }

    public function testTagAndMetadataAndMessageStreamHeaders()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $email->getHeaders()->add(new MessageStreamHeader('broadcasts'));

        $transport = new PostmarkSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkSmtpTransport::class, 'addPostmarkHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(6, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
        $this->assertSame('X-PM-KeepID: true', $email->getHeaders()->get('X-PM-KeepID')->toString());
        $this->assertSame('X-PM-Tag: password-reset', $email->getHeaders()->get('X-PM-Tag')->toString());
        $this->assertSame('X-PM-Metadata-Color: blue', $email->getHeaders()->get('X-PM-Metadata-Color')->toString());
        $this->assertSame('X-PM-Metadata-Client-ID: 12345', $email->getHeaders()->get('X-PM-Metadata-Client-ID')->toString());
        $this->assertSame('X-PM-Message-Stream: broadcasts', $email->getHeaders()->get('X-PM-Message-Stream')->toString());
    }

    public function testMultipleTagsAreNotAllowed()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('tag1'));
        $email->getHeaders()->add(new TagHeader('tag2'));

        $transport = new PostmarkSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkSmtpTransport::class, 'addPostmarkHeaders');

        $this->expectException(TransportException::class);

        $method->invoke($transport, $email);
    }

    public function testTrackingHeader()
    {
        $transport = new PostmarkSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkSmtpTransport::class, 'addPostmarkHeaders');

        $enabled = new Email();
        $enabled->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $method->invoke($transport, $enabled);
        $this->assertSame('X-PM-TrackOpens: true', $enabled->getHeaders()->get('X-PM-TrackOpens')->toString());
        $this->assertSame('X-PM-TrackLinks: HtmlAndText', $enabled->getHeaders()->get('X-PM-TrackLinks')->toString());

        $disabled = new Email();
        $disabled->getHeaders()->add(new TrackingHeader(opens: false, clicks: false));
        $method->invoke($transport, $disabled);
        $this->assertSame('X-PM-TrackOpens: false', $disabled->getHeaders()->get('X-PM-TrackOpens')->toString());
        $this->assertSame('X-PM-TrackLinks: None', $disabled->getHeaders()->get('X-PM-TrackLinks')->toString());
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependently()
    {
        $transport = new PostmarkSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkSmtpTransport::class, 'addPostmarkHeaders');

        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(opens: false));
        $method->invoke($transport, $email);

        $this->assertSame('X-PM-TrackOpens: false', $email->getHeaders()->get('X-PM-TrackOpens')->toString());
        $this->assertNull($email->getHeaders()->get('X-PM-TrackLinks'));
    }

    public function testExplicitPostmarkTrackingHeaderWinsOverTrackingHeader()
    {
        $transport = new PostmarkSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkSmtpTransport::class, 'addPostmarkHeaders');

        $email = new Email();
        $email->getHeaders()->addTextHeader('X-PM-TrackOpens', 'false');
        $email->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $method->invoke($transport, $email);

        $this->assertSame(1, iterator_count($email->getHeaders()->all('X-PM-TrackOpens')));
        $this->assertSame('X-PM-TrackOpens: false', $email->getHeaders()->get('X-PM-TrackOpens')->toString());
        $this->assertSame('X-PM-TrackLinks: HtmlAndText', $email->getHeaders()->get('X-PM-TrackLinks')->toString());
        $this->assertFalse($email->getHeaders()->has('X-Track'));
    }

    public function testPlainTextTrackingHeaderIsResolved()
    {
        $transport = new PostmarkSmtpTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkSmtpTransport::class, 'addPostmarkHeaders');

        $email = new Email();
        $email->getHeaders()->addTextHeader('X-Track', 'opens=false; clicks=false');
        $method->invoke($transport, $email);

        $this->assertSame('X-PM-TrackOpens: false', $email->getHeaders()->get('X-PM-TrackOpens')->toString());
        $this->assertSame('X-PM-TrackLinks: None', $email->getHeaders()->get('X-PM-TrackLinks')->toString());
        $this->assertFalse($email->getHeaders()->has('X-Track'));
    }
}
