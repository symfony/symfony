<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context\Encoder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\Encoder\YamlEncoderContextBuilder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class YamlEncoderContextBuilderTest extends TestCase
{
    private YamlEncoderContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new YamlEncoderContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withIndentLevel($values[YamlEncoder::YAML_INDENT])
            ->withInlineThreshold($values[YamlEncoder::YAML_INLINE])
            ->withFlags($values[YamlEncoder::YAML_FLAGS])
            ->withPreservedEmptyObjects($values[YamlEncoder::PRESERVE_EMPTY_OBJECTS])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>|}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            YamlEncoder::YAML_INDENT => 4,
            YamlEncoder::YAML_INLINE => 16,
            YamlEncoder::YAML_FLAGS => 128,
            YamlEncoder::PRESERVE_EMPTY_OBJECTS => false,
        ]];

        yield 'With null values' => [[
            YamlEncoder::YAML_INDENT => null,
            YamlEncoder::YAML_INLINE => null,
            YamlEncoder::YAML_FLAGS => null,
            YamlEncoder::PRESERVE_EMPTY_OBJECTS => null,
        ]];
    }
}
