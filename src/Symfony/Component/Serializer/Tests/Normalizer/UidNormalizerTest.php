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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;

class UidNormalizerTest extends TestCase
{
    use ExpectDeprecationTrait;

    /**
     * @var UidNormalizer
     */
    private $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UidNormalizer();
    }

    public function testSupportsNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v1()));
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v3(Uuid::v1(), 'foo')));
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v4()));
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v5(Uuid::v1(), 'foo')));
        $this->assertTrue($this->normalizer->supportsNormalization(Uuid::v6()));
        $this->assertTrue($this->normalizer->supportsNormalization(new Ulid()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public static function normalizeProvider()
    {
        $uidFormats = [null, 'canonical', 'base58', 'base32', 'rfc4122'];
        $data = [
             [
                 UuidV1::fromString('9b7541de-6f87-11ea-ab3c-9da9a81562fc'),
                '9b7541de-6f87-11ea-ab3c-9da9a81562fc',
                '9b7541de-6f87-11ea-ab3c-9da9a81562fc',
                'LCQS8f2p5SDSiAt9V7ZYnF',
                '4VEN0XWVW727NAPF4XN6M1ARQW',
                '9b7541de-6f87-11ea-ab3c-9da9a81562fc',
            ],
            [
                UuidV3::fromString('e576629b-ff34-3642-9c08-1f5219f0d45b'),
                'e576629b-ff34-3642-9c08-1f5219f0d45b',
                'e576629b-ff34-3642-9c08-1f5219f0d45b',
                'VLRwe3qfi66uUAE3mYQ4Dp',
                '75ESH9QZSM6S19R20ZA8CZ1N2V',
                'e576629b-ff34-3642-9c08-1f5219f0d45b',
            ],
            [
                UuidV4::fromString('4126dbc1-488e-4f6e-aadd-775dcbac482e'),
                '4126dbc1-488e-4f6e-aadd-775dcbac482e',
                '4126dbc1-488e-4f6e-aadd-775dcbac482e',
                '93d88pS3fdrDXNR2XxU9nu',
                '214VDW2J4E9XQANQBQBQ5TRJ1E',
                '4126dbc1-488e-4f6e-aadd-775dcbac482e',
            ],
            [
                UuidV5::fromString('18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22'),
                '18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22',
                '18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22',
                '44epMFQYZ9byVSGis5dofo',
                '0RSQSX7TGVBCHTKHA0NF8E5QS2',
                '18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22',
            ],
            [
                UuidV6::fromString('1ea6ecef-eb9a-66fe-b62b-957b45f17e43'),
                '1ea6ecef-eb9a-66fe-b62b-957b45f17e43',
                '1ea6ecef-eb9a-66fe-b62b-957b45f17e43',
                '4nXtvo2iuyYefrqTMhvogn',
                '0YMVPEZTWTCVZBCAWNFD2Z2ZJ3',
                '1ea6ecef-eb9a-66fe-b62b-957b45f17e43',
            ],
            [
                Ulid::fromString('01E4BYF64YZ97MDV6RH0HAMN6X'),
                '01E4BYF64YZ97MDV6RH0HAMN6X',
                '01E4BYF64YZ97MDV6RH0HAMN6X',
                '1BKuy2YWf8Yf9vSkA2wDpg',
                '01E4BYF64YZ97MDV6RH0HAMN6X',
                '017117e7-989e-fa4f-46ec-d88822aa54dd',
            ],
        ];

        foreach ($uidFormats as $i => $uidFormat) {
            foreach ($data as $uidClass => $row) {
                yield [$row[$i + 1], $row[0], $uidFormat];
            }
        }
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalize(string $expected, AbstractUid $uid, ?string $uidFormat)
    {
        $this->assertSame($expected, $this->normalizer->normalize($uid, null, null !== $uidFormat ? [
            'uid_normalization_format' => $uidFormat,
        ] : []));
    }

    public static function dataProvider()
    {
        return [
            ['9b7541de-6f87-11ea-ab3c-9da9a81562fc', UuidV1::class],
            ['e576629b-ff34-3642-9c08-1f5219f0d45b', UuidV3::class],
            ['4126dbc1-488e-4f6e-aadd-775dcbac482e', UuidV4::class],
            ['18cdf3d3-ea1b-5b23-a9c5-40abd0e2df22', UuidV5::class],
            ['1ea6ecef-eb9a-66fe-b62b-957b45f17e43', UuidV6::class],
            ['01E4BYF64YZ97MDV6RH0HAMN6X', Ulid::class],
            ['01FPT3YXZXJ1J437FES7CR5BCB', TestCustomUid::class],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testSupportsDenormalization($uuidString, $class)
    {
        $this->assertTrue($this->normalizer->supportsDenormalization($uuidString, $class));
    }

    public function testSupportsDenormalizationForNonUid()
    {
        $this->assertFalse($this->normalizer->supportsDenormalization('foo', \stdClass::class));
    }

    /**
     * @group legacy
     */
    public function testSupportOurAbstractUid()
    {
        $this->expectDeprecation('Since symfony/serializer 6.1: Supporting denormalization for the "Symfony\Component\Uid\AbstractUid" type in "Symfony\Component\Serializer\Normalizer\UidNormalizer" is deprecated, use one of "Symfony\Component\Uid\AbstractUid" child class instead.');

        $this->assertTrue($this->normalizer->supportsDenormalization('1ea6ecef-eb9a-66fe-b62b-957b45f17e43', AbstractUid::class));
    }

    public function testSupportCustomAbstractUid()
    {
        $this->assertTrue($this->normalizer->supportsDenormalization('ccc', TestAbstractCustomUid::class));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testDenormalize($uuidString, $class)
    {
        $this->assertEquals($class::fromString($uuidString), $this->normalizer->denormalize($uuidString, $class));
    }

    /**
     * @group legacy
     */
    public function testDenormalizeOurAbstractUid()
    {
        $this->expectDeprecation('Since symfony/serializer 6.1: Denormalizing to an abstract class in "Symfony\Component\Serializer\Normalizer\UidNormalizer" is deprecated.');

        $this->assertEquals(Uuid::fromString($uuidString = '1ea6ecef-eb9a-66fe-b62b-957b45f17e43'), $this->normalizer->denormalize($uuidString, AbstractUid::class));
    }

    /**
     * @group legacy
     */
    public function testDenormalizeCustomAbstractUid()
    {
        $this->expectDeprecation('Since symfony/serializer 6.1: Denormalizing to an abstract class in "Symfony\Component\Serializer\Normalizer\UidNormalizer" is deprecated.');

        $this->assertEquals(Uuid::fromString($uuidString = '1ea6ecef-eb9a-66fe-b62b-957b45f17e43'), $this->normalizer->denormalize($uuidString, TestAbstractCustomUid::class));
    }

    public function testNormalizeWithNormalizationFormatPassedInConstructor()
    {
        $uidNormalizer = new UidNormalizer([
            'uid_normalization_format' => 'rfc4122',
        ]);
        $ulid = Ulid::fromString('01ETWV01C0GYQ5N92ZK7QRGB10');

        $this->assertSame('0176b9b0-0580-87ae-5aa4-5f99ef882c20', $uidNormalizer->normalize($ulid));
        $this->assertSame('01ETWV01C0GYQ5N92ZK7QRGB10', $uidNormalizer->normalize($ulid, null, [
            'uid_normalization_format' => 'canonical',
        ]));
    }

    public function testNormalizeWithNormalizationFormatNotValid()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "ccc" format is not valid.');

        $this->normalizer->normalize(new Ulid(), null, [
            'uid_normalization_format' => 'ccc',
        ]);
    }
}

class TestCustomUid extends Ulid
{
}

abstract class TestAbstractCustomUid extends Ulid
{
}
