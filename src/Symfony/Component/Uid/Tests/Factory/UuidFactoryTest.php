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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;

final class UuidFactoryTest extends TestCase
{
    public function testCreateNamedDefaultVersion()
    {
        self::assertInstanceOf(UuidV5::class, (new UuidFactory())->nameBased('6f80c216-0492-4421-bd82-c10ab929ae84')->create('foo'));
        self::assertInstanceOf(UuidV3::class, (new UuidFactory(6, 6, 3))->nameBased('6f80c216-0492-4421-bd82-c10ab929ae84')->create('foo'));
    }

    public function testCreateNamed()
    {
        $uuidFactory = new UuidFactory();

        // Test custom namespace
        $uuid1 = $uuidFactory->nameBased('6f80c216-0492-4421-bd82-c10ab929ae84')->create('foo');
        self::assertInstanceOf(UuidV5::class, $uuid1);
        self::assertSame('d521ceb7-3e31-5954-b873-92992c697ab9', (string) $uuid1);

        // Test default namespace override
        $uuid2 = $uuidFactory->nameBased(Uuid::v4())->create('foo');
        self::assertFalse($uuid1->equals($uuid2));

        // Test version override
        $uuidFactory = new UuidFactory(6, 6, 3, 4, new NilUuid(), '6f80c216-0492-4421-bd82-c10ab929ae84');
        $uuid3 = $uuidFactory->nameBased()->create('foo');
        self::assertInstanceOf(UuidV3::class, $uuid3);
    }

    public function testCreateTimedDefaultVersion()
    {
        self::assertInstanceOf(UuidV6::class, (new UuidFactory())->timeBased()->create());
        self::assertInstanceOf(UuidV1::class, (new UuidFactory(6, 1))->timeBased()->create());
    }

    public function testCreateTimed()
    {
        $uuidFactory = new UuidFactory(6, 6, 5, 4, '6f80c216-0492-4421-bd82-c10ab929ae84');

        // Test custom timestamp
        $uuid1 = $uuidFactory->timeBased()->create(new \DateTime('@1611076938.057800'));
        self::assertInstanceOf(UuidV6::class, $uuid1);
        self::assertSame('1611076938.057800', $uuid1->getDateTime()->format('U.u'));
        self::assertSame('c10ab929ae84', $uuid1->getNode());

        // Test default node override
        $uuid2Factory = $uuidFactory->timeBased('7c1ede70-3586-48ed-a984-23c8018d9174');
        self::assertSame('1eb5a7ae-17e1-62d0-a984-23c8018d9174', (string) $uuid2Factory->create(new \DateTime('@1611076938.057800')));
        self::assertSame('23c8018d9174', substr($uuid2Factory->create(), 24));
        self::assertNotSame('a984', substr($uuid2Factory->create(), 19, 4));

        // Test version override
        $uuid3 = (new UuidFactory(6, 1))->timeBased()->create();
        self::assertInstanceOf(UuidV1::class, $uuid3);

        // Test negative timestamp and round
        $uuid4 = $uuidFactory->timeBased()->create(new \DateTime('@-12219292800'));
        self::assertSame('-12219292800.000000', $uuid4->getDateTime()->format('U.u'));
    }

    public function testInvalidCreateTimed()
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The given UUID date cannot be earlier than 1582-10-15.');

        (new UuidFactory())->timeBased()->create(new \DateTime('@-12219292800.001000'));
    }

    public function testCreateRandom()
    {
        self::assertInstanceOf(UuidV4::class, (new UuidFactory())->randomBased()->create());
    }

    public function testCreateNamedWithNamespacePredefinedKeyword()
    {
        self::assertSame('1002657d-3019-59b1-96dc-afc2a3e57c61', (string) (new UuidFactory())->nameBased('dns')->create('symfony.com'));
    }
}
