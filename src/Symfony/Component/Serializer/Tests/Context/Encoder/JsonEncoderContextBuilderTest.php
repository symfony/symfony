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
use Symfony\Component\Serializer\Context\Encoder\JsonEncoderContextBuilder;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class JsonEncoderContextBuilderTest extends TestCase
{
    private JsonEncoderContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new JsonEncoderContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withEncodeOptions($values[JsonEncode::OPTIONS])
            ->withDecodeOptions($values[JsonDecode::OPTIONS])
            ->withAssociative($values[JsonDecode::ASSOCIATIVE])
            ->withRecursionDepth($values[JsonDecode::RECURSION_DEPTH])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>|}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            JsonEncode::OPTIONS => \JSON_PRETTY_PRINT,
            JsonDecode::OPTIONS => \JSON_BIGINT_AS_STRING,
            JsonDecode::ASSOCIATIVE => true,
            JsonDecode::RECURSION_DEPTH => 1024,
        ]];

        yield 'With null values' => [[
            JsonEncode::OPTIONS => null,
            JsonDecode::OPTIONS => null,
            JsonDecode::ASSOCIATIVE => null,
            JsonDecode::RECURSION_DEPTH => null,
        ]];
    }
}
