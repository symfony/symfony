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
use Symfony\Component\Serializer\Context\Normalizer\DateTimeNormalizerContextBuilder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class DateTimeNormalizerContextBuilderTest extends TestCase
{
    private DateTimeNormalizerContextBuilder $contextBuilder;

    protected function setUp(): void
    {
        $this->contextBuilder = new DateTimeNormalizerContextBuilder();
    }

    /**
     * @dataProvider withersDataProvider
     *
     * @param array<string, mixed> $values
     */
    public function testWithers(array $values)
    {
        $context = $this->contextBuilder
            ->withFormat($values[DateTimeNormalizer::FORMAT_KEY])
            ->withTimezone($values[DateTimeNormalizer::TIMEZONE_KEY])
            ->toArray();

        $this->assertEquals($values, $context);
    }

    /**
     * @return iterable<array{0: array<string, mixed>}>
     */
    public static function withersDataProvider(): iterable
    {
        yield 'With values' => [[
            DateTimeNormalizer::FORMAT_KEY => 'format',
            DateTimeNormalizer::TIMEZONE_KEY => new \DateTimeZone('GMT'),
        ]];

        yield 'With null values' => [[
            DateTimeNormalizer::FORMAT_KEY => null,
            DateTimeNormalizer::TIMEZONE_KEY => null,
        ]];
    }

    public function testCastTimezoneStringToTimezone()
    {
        $this->assertEquals([DateTimeNormalizer::TIMEZONE_KEY => new \DateTimeZone('GMT')], $this->contextBuilder->withTimezone('GMT')->toArray());
    }

    public function testCannotSetInvalidTimezone()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->contextBuilder->withTimezone('not a timezone');
    }
}
