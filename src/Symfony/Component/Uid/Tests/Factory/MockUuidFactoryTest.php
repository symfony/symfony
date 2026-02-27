<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Tests\Factory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Exception\LogicException;
use Symfony\Component\Uid\Factory\MockUuidFactory;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Uid\UuidV8;

class MockUuidFactoryTest extends TestCase
{
    #[DataProvider('provideSequences')]
    public function testCreate(iterable $sequence, array $expected)
    {
        $factory = new MockUuidFactory($sequence);
        foreach ($expected as $expectedUuid) {
            $this->assertEquals($expectedUuid, $factory->create());
        }
    }

    public static function provideSequences(): \Generator
    {
        $uuid1String = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $uuid3String = '6ba7b810-9dad-31d1-80b4-00c04fd430c8';
        $uuid4String = '6ba7b810-9dad-41d1-80b4-00c04fd430c8';
        $uuid5String = '6ba7b810-9dad-51d1-80b4-00c04fd430c8';
        $uuid6String = '6ba7b810-9dad-61d1-80b4-00c04fd430c8';
        $uuid7String = '6ba7b810-9dad-71d1-80b4-00c04fd430c8';
        $uuid8String = '6ba7b810-9dad-81d1-80b4-00c04fd430c8';

        $uuid1 = UuidV1::fromString($uuid1String);
        $uuid3 = UuidV3::fromString($uuid3String);
        $uuid4 = UuidV4::fromString($uuid4String);
        $uuid5 = UuidV5::fromString($uuid5String);
        $uuid6 = UuidV6::fromString($uuid6String);
        $uuid7 = UuidV7::fromString($uuid7String);
        $uuid8 = UuidV8::fromString($uuid8String);

        yield 'object sequence' => [
            [$uuid1, $uuid3, $uuid4, $uuid5, $uuid6, $uuid7, $uuid8],
            [$uuid1, $uuid3, $uuid4, $uuid5, $uuid6, $uuid7, $uuid8],
        ];
        yield 'string sequence' => [
            [
                $uuid1String,
                $uuid3String,
                $uuid4String,
                $uuid5String,
                $uuid6String,
                $uuid7String,
                $uuid8String,
            ],
            [$uuid1, $uuid3, $uuid4, $uuid5, $uuid6, $uuid7, $uuid8],
        ];
        yield 'mixed sequence' => [
            [
                $uuid1,
                $uuid3String,
                $uuid4,
                $uuid5String,
                $uuid6,
                $uuid7String,
                $uuid8,
            ],
            [$uuid1, $uuid3, $uuid4, $uuid5, $uuid6, $uuid7, $uuid8],
        ];
    }

    public function testCreateThrowsExceptionOnInvalidUuidType()
    {
        $factory = new MockUuidFactory([123]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Next UUID in sequence is not a valid UUID string or object: "int" given.');

        $factory->create();
    }

    public function testCreateThrowsExceptionWhenSequenceIsEmpty()
    {
        $factory = new MockUuidFactory([
            UuidV1::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'),
            '6ba7b810-9dad-31d1-80b4-00c04fd430c8',
        ]);

        $factory->create();
        $factory->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No more UUIDs in sequence');

        $factory->create();
    }

    public function testRandomBasedReturnsUuidV4FromSequence()
    {
        $uuid1 = UuidV4::fromString('6ba7b810-9dad-41d1-80b4-00c04fd430c8');
        $uuid2 = UuidV4::fromString('9d235ae3-a819-41e3-9216-7858734f543d');
        $uuid3 = '3d813cbb-47fb-4f2e-8c1a-6b5f9e8f1e3b';
        $factory = new MockUuidFactory([
            $uuid1,
            $uuid2,
            $uuid3,
        ]);
        $randomFactory = $factory->randomBased();

        $this->assertSame($uuid1, $randomFactory->create());
        $this->assertSame($uuid2, $randomFactory->create());
        $this->assertEquals(UuidV4::fromString($uuid3), $randomFactory->create());
    }

    public function testRandomBasedThrowsExceptionWhenSequenceIsEmpty()
    {
        $factory = new MockUuidFactory([
            UuidV4::fromString('6ba7b810-9dad-41d1-80b4-00c04fd430c8'),
            '3d813cbb-47fb-4f2e-8c1a-6b5f9e8f1e3b',
        ]);
        $randomFactory = $factory->randomBased();

        $randomFactory->create();
        $randomFactory->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No more UUIDs in sequence');

        $randomFactory->create();
    }

    public function testRandomBasedThrowsExceptionOnInvalidType()
    {
        $factory = new MockUuidFactory([
            UuidV5::fromString('6ba7b810-9dad-51d1-80b4-00c04fd430c8'),
        ]);
        $randomFactory = $factory->randomBased();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Next UUID in sequence is not a UuidV4: "Symfony\\Component\\Uid\\UuidV5" given.');

        $randomFactory->create();
    }

    public function testTimeBasedReturnsUuidFromSequence()
    {
        $uuid1 = UuidV1::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
        $uuid2 = UuidV6::fromString('9d235ae3-a819-61e3-9216-7858734f543d');
        $uuid3 = UuidV7::fromString('3d813cbb-47fb-71e2-8c1a-6b5f9e8f1e3b');
        $uuid4 = '3d813cbb-47fb-11e2-8c1a-6b5f9e8f1e3b';
        $factory = new MockUuidFactory([
            $uuid1,
            $uuid2,
            $uuid3,
            $uuid4,
        ]);
        $timeFactory = $factory->timeBased();

        $this->assertSame($uuid1, $timeFactory->create());
        $this->assertSame($uuid2, $timeFactory->create());
        $this->assertSame($uuid3, $timeFactory->create());
        $this->assertEquals(UuidV1::fromString($uuid4), $timeFactory->create());
    }

    public function testTimeBasedThrowsExceptionWhenSequenceIsEmpty()
    {
        $factory = new MockUuidFactory([
            UuidV1::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'),
            '3d813cbb-47fb-11e2-8c1a-6b5f9e8f1e3b',
        ]);
        $timeFactory = $factory->timeBased();

        $timeFactory->create();
        $timeFactory->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No more UUIDs in sequence');

        $timeFactory->create();
    }

    public function testTimeBasedThrowsExceptionOnInvalidType()
    {
        $factory = new MockUuidFactory([
            UuidV4::fromString('6ba7b810-9dad-41d1-80b4-00c04fd430c8'),
        ]);
        $timeFactory = $factory->timeBased();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Next UUID in sequence is not a Uuid and TimeBasedUidInterface: "Symfony\\Component\\Uid\\UuidV4" given.');

        $timeFactory->create();
    }

    public function testTimeBasedThrowsExceptionOnMismatchedTime()
    {
        $factory = new MockUuidFactory([
            UuidV1::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8'),
        ]);
        $timeFactory = $factory->timeBased();

        $wrongTime = new \DateTime();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Next UUID in sequence does not match the expected time:');

        $timeFactory->create($wrongTime);
    }

    public function testNameBasedReturnsUuidFromSequence()
    {
        $namespace = 'dns';
        $uuid1 = UuidV5::fromString('4be0643f-1d98-573b-97cd-ca98a65347dd');
        $uuid2 = UuidV3::fromString('45a113ac-c7f2-30b0-90a5-a399ab912716');

        $factory = new MockUuidFactory([
            $uuid1,
            $uuid2,
        ]);

        $this->assertSame($uuid1, $factory->nameBased($namespace)->create('test'));
        $this->assertSame($uuid2, $factory->nameBased($namespace)->create('test'));
    }

    public function testNameBasedThrowsExceptionOnNullNamespace()
    {
        $uuid = UuidV5::fromString('4be0643f-1d98-573b-97cd-ca98a65347dd');
        $factory = new MockUuidFactory([
            $uuid,
        ]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A namespace should be defined when using "Symfony\Component\Uid\Factory\MockUuidFactory::nameBased()".');

        $factory->nameBased()->create('test');
    }

    public function testNameBasedThrowsExceptionWhenSequenceIsEmpty()
    {
        $namespace = 'dns';
        $uuid1 = UuidV5::fromString('4be0643f-1d98-573b-97cd-ca98a65347dd');
        $uuid2 = UuidV3::fromString('45a113ac-c7f2-30b0-90a5-a399ab912716');

        $factory = new MockUuidFactory([
            $uuid1,
            $uuid2,
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No more UUIDs in sequence');

        $factory->nameBased($namespace)->create('test');
        $factory->nameBased($namespace)->create('test');
        $factory->nameBased($namespace)->create('test');
    }

    public function testNameBasedThrowsExceptionOnInvalidType()
    {
        $uuid = UuidV7::fromString('4be0643f-1d98-773b-97cd-ca98a65347dd');

        $factory = new MockUuidFactory([
            $uuid,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Next UUID in sequence is not a UuidV5 or UuidV3: "Symfony\\Component\\Uid\\UuidV7".');

        $factory->nameBased('dns')->create('test');
    }

    public function testNameBasedThrowsExceptionOnMismatchedNamespace()
    {
        $uuid = UuidV5::fromString('4be0643f-1d98-573b-97cd-ca98a65347dd');

        $factory = new MockUuidFactory([
            $uuid,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Next UUID in sequence does not match the expected named UUID: "4be0643f-1d98-573b-97cd-ca98a65347dd" != "da5b8893-d6ca-5c1c-9a9c-91f40a2a3649".');

        $factory->nameBased('url')->create('test');
    }

    public function testNameBasedThrowsExceptionOnMismatchedName()
    {
        $uuid = UuidV5::fromString('4be0643f-1d98-573b-97cd-ca98a65347dd');

        $factory = new MockUuidFactory([
            $uuid,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Next UUID in sequence does not match the expected named UUID: "4be0643f-1d98-573b-97cd-ca98a65347dd" != "c24b99f4-536e-589d-83e7-dd6ca9eef390".');

        $factory->nameBased('dns')->create('different-name');
    }
}
