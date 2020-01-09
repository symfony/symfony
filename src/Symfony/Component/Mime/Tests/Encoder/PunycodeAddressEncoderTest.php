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
use Symfony\Component\Mime\Encoder\PunycodeAddressEncoder;
use Symfony\Component\Mime\Exception\AddressEncoderException;

class PunycodeAddressEncoderTest extends TestCase
{
    /**
     * @dataProvider provideData
     */
    public function testEncodeString(string $address, string $expected)
    {
        $encoder = new PunycodeAddressEncoder();

        $this->assertSame($expected, $encoder->encodeString($address));
    }

    public function provideData()
    {
        return [
            // as-is
            ['invalid', 'invalid'],
            ['azjezz@void.tn', 'azjezz@void.tn'],
            // punycode encoded
            ['🐘@symfony.com', 'xn--go8h@symfony.com'],
            ['saif.gmati@symfony.تونس', 'saif.gmati@symfony.xn--pgbs0dh'],
            ['张伟@symfony.com', 'xn--cpqy30b@symfony.com'],
            ['foo@🐘.php', 'foo@xn--go8h.php'],
        ];
    }

    public function testEncodeStringThrowsForInvalidIDNAddress()
    {
        $this->expectException(AddressEncoderException::class);
        $this->expectExceptionMessage('Unsupported IDN address "azjezz@void.tn..".');

        (new PunycodeAddressEncoder())->encodeString('azjezz@void.tn..');
    }
}
