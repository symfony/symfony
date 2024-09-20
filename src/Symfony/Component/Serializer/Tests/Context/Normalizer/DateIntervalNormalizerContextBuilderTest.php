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
use Symfony\Component\Serializer\Context\Normalizer\DateIntervalNormalizerContextBuilder;
use Symfony\Component\Serializer\Normalizer\DateIntervalNormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class DateIntervalNormalizerContextBuilderTest extends TestCase
{
    private DateIntervalNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new DateIntervalNormalizerContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withFormat($values[DateIntervalNormalizer::FORMAT_KEY])
            ->toArray();

        $this->assertSame($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            DateIntervalNormalizer::FORMAT_KEY => 'format',
        ]];

        yield 'With null values' => [[
            DateIntervalNormalizer::FORMAT_KEY => null,
        ]];
    }
}
