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
}
