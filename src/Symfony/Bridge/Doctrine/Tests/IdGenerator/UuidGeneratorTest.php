<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\IdGenerator;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV6;

class UuidGeneratorTest extends TestCase
{
    public function testUuidCanBeGenerated()
    {
        $em = (new \ReflectionClass(EntityManager::class))->newInstanceWithoutConstructor();
        $generator = new UuidGenerator();
        $uuid = $generator->generate($em, new Entity());

        $this->assertInstanceOf(Uuid::class, $uuid);
    }

    public function testCustomUuidfactory()
    {
        $uuid = new UuidV4();
        $em = (new \ReflectionClass(EntityManager::class))->newInstanceWithoutConstructor();
        $factory = $this->createMock(UuidFactory::class);
        $factory->expects($this->any())
            ->method('create')
            ->willReturn($uuid);
        $generator = new UuidGenerator($factory);

        $this->assertSame($uuid, $generator->generate($em, new Entity()));
    }

    public function testUuidfactory()
    {
        $em = (new \ReflectionClass(EntityManager::class))->newInstanceWithoutConstructor();
        $generator = new UuidGenerator();
        $this->assertInstanceOf(UuidV6::class, $generator->generate($em, new Entity()));

        $generator = $generator->randomBased();
        $this->assertInstanceOf(UuidV4::class, $generator->generate($em, new Entity()));

        $generator = $generator->timeBased();
        $this->assertInstanceOf(UuidV6::class, $generator->generate($em, new Entity()));

        $generator = $generator->nameBased('prop1', Uuid::NAMESPACE_OID);
        $this->assertEquals(Uuid::v5(new Uuid(Uuid::NAMESPACE_OID), '3'), $generator->generate($em, new Entity()));

        $generator = $generator->nameBased('prop2', Uuid::NAMESPACE_OID);
        $this->assertEquals(Uuid::v5(new Uuid(Uuid::NAMESPACE_OID), '2'), $generator->generate($em, new Entity()));

        $generator = $generator->nameBased('getProp4', Uuid::NAMESPACE_OID);
        $this->assertEquals(Uuid::v5(new Uuid(Uuid::NAMESPACE_OID), '4'), $generator->generate($em, new Entity()));

        $factory = new UuidFactory(6, 6, 5, 5, null, Uuid::NAMESPACE_OID);
        $generator = new UuidGenerator($factory);
        $generator = $generator->nameBased('prop1');
        $this->assertEquals(Uuid::v5(new Uuid(Uuid::NAMESPACE_OID), '3'), $generator->generate($em, new Entity()));
    }
}

class Entity
{
    public $prop1 = 1;
    public $prop2 = 2;

    public function prop1()
    {
        return 3;
    }

    public function getProp4()
    {
        return 4;
    }
}
