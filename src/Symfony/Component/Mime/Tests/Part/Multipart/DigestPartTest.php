<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Part\Multipart;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\MessagePart;
use Symfony\Component\Mime\Part\Multipart\DigestPart;

class DigestPartTest extends TestCase
{
    public function testConstructor()
    {
        $r = new DigestPart($a = new MessagePart(new Message()), $b = new MessagePart(new Message()));
        $this->assertEquals('multipart', $r->getMediaType());
        $this->assertEquals('digest', $r->getMediaSubtype());
        $this->assertEquals([$a, $b], $r->getParts());
    }
}
