<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Encoder\QpContentEncoder;

class QpContentEncoderTest extends TestCase
{
    public function testReplaceLastChar()
    {
        $encoder = new QpContentEncoder();

        $this->assertSame('message=09', $encoder->encodeString('message'.chr(0x09)));
        $this->assertSame('message=20', $encoder->encodeString('message'.chr(0x20)));
    }
}
