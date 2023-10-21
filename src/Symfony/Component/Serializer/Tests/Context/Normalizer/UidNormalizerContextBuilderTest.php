<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\Normalizer\UidNormalizerContextBuilder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class UidNormalizerContextBuilderTest extends TestCase
{
    private UidNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new UidNormalizerContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withNormalizationFormat($values[UidNormalizer::NORMALIZATION_FORMAT_KEY])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            UidNormalizer::NORMALIZATION_FORMAT_KEY => UidNormalizer::NORMALIZATION_FORMAT_BASE32,
        ]];

        yield 'With null values' => [[
            UidNormalizer::NORMALIZATION_FORMAT_KEY => null,
        ]];
    }

    public function testCannotSetInvalidUidNormalizationFormat()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->contextBuilder->withNormalizationFormat('invalid format');
    }
}
