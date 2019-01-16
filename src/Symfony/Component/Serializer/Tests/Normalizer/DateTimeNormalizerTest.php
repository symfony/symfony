<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class DateTimeNormalizerTest extends TestCase
{
    /**
     * @var DateTimeNormalizer
     */
    private $normalizer;

    protected function setUp()
    {
        $this->normalizer = new DateTimeNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new \DateTime()));
        $this->assertTrue($this->normalizer->supportsNormalization(new \DateTimeImmutable()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $this->assertEquals('2016-01-01T00:00:00+00:00', $this->normalizer->normalize(new \DateTime('2016/01/01', new \DateTimeZone('UTC'))));
        $this->assertEquals('2016-01-01T00:00:00+00:00', $this->normalizer->normalize(new \DateTimeImmutable('2016/01/01', new \DateTimeZone('UTC'))));
    }

    public function testNormalizeUsingFormatPassedInContext()
    {
        $this->assertEquals('2016', $this->normalizer->normalize(new \DateTime('2016/01/01'), null, [DateTimeNormalizer::FORMAT_KEY => 'Y']));
    }

    public function testNormalizeUsingFormatPassedInConstructor()
    {
        $this->doTestNormalizeUsingFormatPassedInConstructor();
    }

    public function testLegacyNormalizeUsingFormatPassedInConstructor()
    {
        $this->doTestNormalizeUsingFormatPassedInConstructor(true);
    }

    private function doTestNormalizeUsingFormatPassedInConstructor(bool $legacy = false)
    {
        $normalizer = $legacy ? new DateTimeNormalizer('y') : new DateTimeNormalizer([DateTimeNormalizer::FORMAT_KEY => 'y']);
        $this->assertEquals('16', $normalizer->normalize(new \DateTime('2016/01/01', new \DateTimeZone('UTC'))));
    }

    public function testNormalizeUsingTimeZonePassedInConstructor()
    {
        $this->doTestNormalizeUsingTimeZonePassedInConstructor();
    }

    public function testLegacyNormalizeUsingTimeZonePassedInConstructor()
    {
        $this->doTestNormalizeUsingTimeZonePassedInConstructor(true);
    }

    private function doTestNormalizeUsingTimeZonePassedInConstructor(bool $legacy = false)
    {
        if ($legacy) {
            $normalizer = new DateTimeNormalizer(\DateTime::RFC3339, new \DateTimeZone('Japan'));
        } else {
            $normalizer = new DateTimeNormalizer([DateTimeNormalizer::TIMEZONE_KEY => new \DateTimeZone('Japan')]);
        }

        $this->assertSame('2016-12-01T00:00:00+09:00', $normalizer->normalize(new \DateTime('2016/12/01', new \DateTimeZone('Japan'))));
        $this->assertSame('2016-12-01T09:00:00+09:00', $normalizer->normalize(new \DateTime('2016/12/01', new \DateTimeZone('UTC'))));
    }

    /**
     * @dataProvider normalizeUsingTimeZonePassedInContextProvider
     */
    public function testNormalizeUsingTimeZonePassedInContext($expected, $input, $timezone)
    {
        $this->assertSame($expected, $this->normalizer->normalize($input, null, [
            DateTimeNormalizer::TIMEZONE_KEY => $timezone,
        ]));
    }

    public function normalizeUsingTimeZonePassedInContextProvider()
    {
        yield ['2016-12-01T00:00:00+00:00', new \DateTime('2016/12/01', new \DateTimeZone('UTC')), null];
        yield ['2016-12-01T00:00:00+09:00', new \DateTime('2016/12/01', new \DateTimeZone('Japan')), new \DateTimeZone('Japan')];
        yield ['2016-12-01T09:00:00+09:00', new \DateTime('2016/12/01', new \DateTimeZone('UTC')), new \DateTimeZone('Japan')];
        yield ['2016-12-01T09:00:00+09:00', new \DateTimeImmutable('2016/12/01', new \DateTimeZone('UTC')), new \DateTimeZone('Japan')];
    }

    /**
     * @dataProvider normalizeUsingTimeZonePassedInContextAndExpectedFormatWithMicrosecondsProvider
     */
    public function testNormalizeUsingTimeZonePassedInContextAndFormattedWithMicroseconds($expected, $expectedFormat, $input, $timezone)
    {
        $this->assertSame(
            $expected,
            $this->normalizer->normalize(
                $input,
                null,
                [
                    DateTimeNormalizer::TIMEZONE_KEY => $timezone,
                    DateTimeNormalizer::FORMAT_KEY => $expectedFormat,
                ]
            )
        );
    }

    public function normalizeUsingTimeZonePassedInContextAndExpectedFormatWithMicrosecondsProvider()
    {
        yield [
            '2018-12-01T18:03:06.067634',
            'Y-m-d\TH:i:s.u',
            \DateTime::createFromFormat(
                'Y-m-d\TH:i:s.u',
                '2018-12-01T18:03:06.067634',
                new \DateTimeZone('UTC')
            ),
            null,
        ];

        yield [
            '2018-12-01T18:03:06.067634',
            'Y-m-d\TH:i:s.u',
            \DateTime::createFromFormat(
                'Y-m-d\TH:i:s.u',
                '2018-12-01T18:03:06.067634',
                new \DateTimeZone('UTC')
            ),
            new \DateTimeZone('UTC'),
        ];

        yield [
            '2018-12-01T19:03:06.067634+01:00',
            'Y-m-d\TH:i:s.uP',
            \DateTimeImmutable::createFromFormat(
                'Y-m-d\TH:i:s.u',
                '2018-12-01T18:03:06.067634',
                new \DateTimeZone('UTC')
            ),
            new \DateTimeZone('Europe/Rome'),
        ];

        yield [
            '2018-12-01T20:03:06.067634+02:00',
            'Y-m-d\TH:i:s.uP',
            \DateTime::createFromFormat(
                'Y-m-d\TH:i:s.u',
                '2018-12-01T18:03:06.067634',
                new \DateTimeZone('UTC')
            ),
            new \DateTimeZone('Europe/Kiev'),
        ];

        yield [
            '2018-12-01T19:03:06.067634',
            'Y-m-d\TH:i:s.u',
            \DateTime::createFromFormat(
                'Y-m-d\TH:i:s.u',
                '2018-12-01T18:03:06.067634',
                new \DateTimeZone('UTC')
            ),
            new \DateTimeZone('Europe/Berlin'),
        ];
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage The object must implement the "\DateTimeInterface".
     */
    public function testNormalizeInvalidObjectThrowsException()
    {
        $this->normalizer->normalize(new \stdClass());
    }

    public function testSupportsDenormalization()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization('2016-01-01T00:00:00+00:00', \DateTimeInterface::class));
        $this->assertTrue($this->normalizer->supportsDenormalization('2016-01-01T00:00:00+00:00', \DateTime::class));
        $this->assertTrue($this->normalizer->supportsDenormalization('2016-01-01T00:00:00+00:00', \DateTimeImmutable::class));
        $this->assertFalse($this->normalizer->supportsDenormalization('foo', 'Bar'));
    }

    public function testDenormalize()
    {
        $this->assertEquals(new \DateTimeImmutable('2016/01/01', new \DateTimeZone('UTC')), $this->normalizer->denormalize('2016-01-01T00:00:00+00:00', \DateTimeInterface::class));
        $this->assertEquals(new \DateTimeImmutable('2016/01/01', new \DateTimeZone('UTC')), $this->normalizer->denormalize('2016-01-01T00:00:00+00:00', \DateTimeImmutable::class));
        $this->assertEquals(new \DateTime('2016/01/01', new \DateTimeZone('UTC')), $this->normalizer->denormalize('2016-01-01T00:00:00+00:00', \DateTime::class));
    }

    public function testDenormalizeUsingTimezonePassedInConstructor()
    {
        $this->doTestDenormalizeUsingTimezonePassedInConstructor();
    }

    public function testLegacyDenormalizeUsingTimezonePassedInConstructor()
    {
        $this->doTestDenormalizeUsingTimezonePassedInConstructor(true);
    }

    private function doTestDenormalizeUsingTimezonePassedInConstructor(bool $legacy = false)
    {
        $timezone = new \DateTimeZone('Japan');
        $expected = new \DateTime('2016/12/01 17:35:00', $timezone);
        $normalizer = $legacy ? new DateTimeNormalizer(null, $timezone) : new DateTimeNormalizer([DateTimeNormalizer::TIMEZONE_KEY => $timezone]);

        $this->assertEquals($expected, $normalizer->denormalize('2016.12.01 17:35:00', \DateTime::class, null, [
            DateTimeNormalizer::FORMAT_KEY => 'Y.m.d H:i:s',
        ]));
    }

    public function testDenormalizeUsingFormatPassedInContext()
    {
        $this->assertEquals(new \DateTimeImmutable('2016/01/01'), $this->normalizer->denormalize('2016.01.01', \DateTimeInterface::class, null, [DateTimeNormalizer::FORMAT_KEY => 'Y.m.d|']));
        $this->assertEquals(new \DateTimeImmutable('2016/01/01'), $this->normalizer->denormalize('2016.01.01', \DateTimeImmutable::class, null, [DateTimeNormalizer::FORMAT_KEY => 'Y.m.d|']));
        $this->assertEquals(new \DateTime('2016/01/01'), $this->normalizer->denormalize('2016.01.01', \DateTime::class, null, [DateTimeNormalizer::FORMAT_KEY => 'Y.m.d|']));
    }

    /**
     * @dataProvider denormalizeUsingTimezonePassedInContextProvider
     */
    public function testDenormalizeUsingTimezonePassedInContext($input, $expected, $timezone, $format = null)
    {
        $actual = $this->normalizer->denormalize($input, \DateTimeInterface::class, null, [
            DateTimeNormalizer::TIMEZONE_KEY => $timezone,
            DateTimeNormalizer::FORMAT_KEY => $format,
        ]);

        $this->assertEquals($expected, $actual);
    }

    public function denormalizeUsingTimezonePassedInContextProvider()
    {
        yield 'with timezone' => [
            '2016/12/01 17:35:00',
            new \DateTimeImmutable('2016/12/01 17:35:00', new \DateTimeZone('Japan')),
            new \DateTimeZone('Japan'),
        ];
        yield 'with timezone as string' => [
            '2016/12/01 17:35:00',
            new \DateTimeImmutable('2016/12/01 17:35:00', new \DateTimeZone('Japan')),
            'Japan',
        ];
        yield 'with format without timezone information' => [
            '2016.12.01 17:35:00',
            new \DateTimeImmutable('2016/12/01 17:35:00', new \DateTimeZone('Japan')),
            new \DateTimeZone('Japan'),
            'Y.m.d H:i:s',
        ];
        yield 'ignored with format with timezone information' => [
            '2016-12-01T17:35:00Z',
            new \DateTimeImmutable('2016/12/01 17:35:00', new \DateTimeZone('UTC')),
            'Europe/Paris',
            \DateTime::RFC3339,
        ];
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDenormalizeInvalidDataThrowsException()
    {
        $this->normalizer->denormalize('invalid date', \DateTimeInterface::class);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     * @expectedExceptionMessage The data is either an empty string or null, you should pass a string that can be parsed with the passed format or a valid DateTime string.
     */
    public function testDenormalizeNullThrowsException()
    {
        $this->normalizer->denormalize(null, \DateTimeInterface::class);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     * @expectedExceptionMessage The data is either an empty string or null, you should pass a string that can be parsed with the passed format or a valid DateTime string.
     */
    public function testDenormalizeEmptyStringThrowsException()
    {
        $this->normalizer->denormalize('', \DateTimeInterface::class);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDenormalizeFormatMismatchThrowsException()
    {
        $this->normalizer->denormalize('2016-01-01T00:00:00+00:00', \DateTimeInterface::class, null, [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d|']);
    }
}
