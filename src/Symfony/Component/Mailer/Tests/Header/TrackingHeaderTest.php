<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Header;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Header\Headers;

class TrackingHeaderTest extends TestCase
{
    public function testBothFlagsDefaultToNull()
    {
        $header = new TrackingHeader();

        $this->assertSame('X-Track', $header->getName());
        $this->assertNull($header->getOpens());
        $this->assertNull($header->getClicks());
        $this->assertSame('X-Track: opens=default; clicks=default', $header->toString());
    }

    public function testFlagsAreExposedAndSerialized()
    {
        $header = new TrackingHeader(opens: true, clicks: false);

        $this->assertTrue($header->getOpens());
        $this->assertFalse($header->getClicks());
        $this->assertSame('X-Track: opens=true; clicks=false', $header->toString());
    }

    public function testFromHeadersReturnsNullWithoutHeader()
    {
        $this->assertNull(TrackingHeader::fromHeaders(new Headers()));
    }

    public function testFromHeadersReturnsTheInstance()
    {
        $headers = new Headers();
        $headers->add($header = new TrackingHeader(opens: true));

        $this->assertSame($header, TrackingHeader::fromHeaders($headers));
    }

    public function testFromHeadersParsesAPlainTextHeader()
    {
        $headers = new Headers();
        $headers->addTextHeader('X-Track', 'opens=false; clicks=default');

        $header = TrackingHeader::fromHeaders($headers);
        $this->assertFalse($header->getOpens());
        $this->assertNull($header->getClicks());
    }

    public function testFromHeadersToleratesMalformedValues()
    {
        $headers = new Headers();
        $headers->addTextHeader('X-Track', 'yes please');

        $header = TrackingHeader::fromHeaders($headers);
        $this->assertNull($header->getOpens());
        $this->assertNull($header->getClicks());
    }
}
