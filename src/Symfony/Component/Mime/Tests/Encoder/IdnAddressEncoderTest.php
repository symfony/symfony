<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mime\Encoder;

use PHPUnit\Framework\TestCase;

class IdnAddressEncoderTest extends TestCase
{
    public function testEncodeString()
    {
        $this->assertSame('test@xn--fuball-cta.test', (new IdnAddressEncoder())->encodeString('test@fußball.test'));
    }
}
