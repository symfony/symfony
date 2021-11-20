<?php

namespace Symfony\Component\Mime\Encoder;

use PHPUnit\Framework\TestCase;

class IdnAddressEncoderTest extends TestCase
{
    public function testEncodeString()
    {
        $this->assertSame('test@xn--fuball-cta.test', (new IdnAddressEncoder())->encodeString('test@fußball.test'));
    }
}
