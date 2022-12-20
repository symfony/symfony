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
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class YamlEncoderTest extends TestCase
{
    public function testEncode()
    {
        $encoder = new YamlEncoder();

        self::assertEquals('foo', $encoder->encode('foo', 'yaml'));
        self::assertEquals('{ foo: 1 }', $encoder->encode(['foo' => 1], 'yaml'));
        self::assertEquals('null', $encoder->encode(new \ArrayObject(['foo' => 1]), 'yaml'));
        self::assertEquals('{ foo: 1 }', $encoder->encode(new \ArrayObject(['foo' => 1]), 'yaml', ['preserve_empty_objects' => true]));
    }

    public function testSupportsEncoding()
    {
        $encoder = new YamlEncoder();

        self::assertTrue($encoder->supportsEncoding('yaml'));
        self::assertTrue($encoder->supportsEncoding('yml'));
        self::assertFalse($encoder->supportsEncoding('json'));
    }

    public function testDecode()
    {
        $encoder = new YamlEncoder();

        self::assertEquals('foo', $encoder->decode('foo', 'yaml'));
        self::assertEquals(['foo' => 1], $encoder->decode('{ foo: 1 }', 'yaml'));
    }

    public function testSupportsDecoding()
    {
        $encoder = new YamlEncoder();

        self::assertTrue($encoder->supportsDecoding('yaml'));
        self::assertTrue($encoder->supportsDecoding('yml'));
        self::assertFalse($encoder->supportsDecoding('json'));
    }

    public function testContext()
    {
        $encoder = new YamlEncoder(new Dumper(), new Parser(), [YamlEncoder::YAML_INLINE => 1, YamlEncoder::YAML_INDENT => 4, YamlEncoder::YAML_FLAGS => Yaml::DUMP_OBJECT | Yaml::PARSE_OBJECT]);

        $obj = new \stdClass();
        $obj->bar = 2;

        $legacyTag = "    foo: !php/object:O:8:\"stdClass\":1:{s:3:\"bar\";i:2;}\n";
        $spacedTag = "    foo: !php/object 'O:8:\"stdClass\":1:{s:3:\"bar\";i:2;}'\n";
        self::assertThat($encoder->encode(['foo' => $obj], 'yaml'), self::logicalOr(self::equalTo($legacyTag), self::equalTo($spacedTag)));
        self::assertEquals('  { foo: null }', $encoder->encode(['foo' => $obj], 'yaml', [YamlEncoder::YAML_INLINE => 0, YamlEncoder::YAML_INDENT => 2, YamlEncoder::YAML_FLAGS => 0]));
        self::assertEquals(['foo' => $obj], $encoder->decode("foo: !php/object 'O:8:\"stdClass\":1:{s:3:\"bar\";i:2;}'", 'yaml'));
        self::assertEquals(['foo' => null], $encoder->decode("foo: !php/object 'O:8:\"stdClass\":1:{s:3:\"bar\";i:2;}'", 'yaml', [YamlEncoder::YAML_FLAGS => 0]));
    }
}
