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

class JsonEncodeTest extends TestCase
{
    private $encode;

    protected function setUp(): void
    {
        $this->encode = new JsonEncode();
    }

    public function testSupportsEncoding(): void
    {
        $this->assertTrue($this->encode->supportsEncoding(JsonEncoder::FORMAT));
        $this->assertFalse($this->encode->supportsEncoding('foobar'));
    }

    /**
     * @dataProvider encodeProvider
     */
    public function testEncode($toEncode, $expected, $context): void
    {
        $this->assertEquals(
            $expected,
            $this->encode->encode($toEncode, JsonEncoder::FORMAT, $context)
        );
    }

    public function encodeProvider()
    {
        return array(
            array(array(), '[]', array()),
            array(array(), '{}', array('json_encode_options' => JSON_FORCE_OBJECT)),
        );
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testEncodeWithError(): void
    {
        $this->encode->encode("\xB1\x31", JsonEncoder::FORMAT);
    }
}
