<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class JsonEncodeTest extends TestCase
{
    private JsonEncode $encode;

    protected function setUp(): void
    {
        $this->encode = new JsonEncode();
    }

    public function testSupportsEncoding()
    {
        $this->assertTrue($this->encode->supportsEncoding(JsonEncoder::FORMAT));
        $this->assertFalse($this->encode->supportsEncoding('foobar'));
    }

    /**
     * @dataProvider encodeProvider
     */
    public function testEncode($toEncode, $expected, $context)
    {
        $this->assertEquals(
            $expected,
            $this->encode->encode($toEncode, JsonEncoder::FORMAT, $context),
        );
    }

    public static function encodeProvider()
    {
        return [
            [[], '[]', []],
            [[], '{}', ['json_encode_options' => \JSON_FORCE_OBJECT]],
            [new \ArrayObject(), '{}', []],
            [new \ArrayObject(['foo' => 'bar']), '{"foo":"bar"}', []],
        ];
    }

    public function testEncodeWithError()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->encode->encode("\xB1\x31", JsonEncoder::FORMAT);
    }
}
