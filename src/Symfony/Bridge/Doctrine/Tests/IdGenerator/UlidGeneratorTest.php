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
use Doctrine\ORM\Mapping\Entity;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Component\Uid\Factory\UlidFactory;
use Symfony\Component\Uid\Ulid;

class UlidGeneratorTest extends TestCase
{
    public function testUlidCanBeGenerated()
    {
        $em = (new \ReflectionClass(EntityManager::class))->newInstanceWithoutConstructor();
        $generator = new UlidGenerator();
        $ulid = $generator->generate($em, new Entity());

        self::assertInstanceOf(Ulid::class, $ulid);
        self::assertTrue(Ulid::isValid($ulid));
    }

    /**
     * @requires function \Symfony\Component\Uid\Factory\UlidFactory::create
     */
    public function testUlidFactory()
    {
        $ulid = new Ulid('00000000000000000000000000');
        $em = (new \ReflectionClass(EntityManager::class))->newInstanceWithoutConstructor();
        $factory = self::createMock(UlidFactory::class);
        $factory->expects(self::any())
            ->method('create')
            ->willReturn($ulid);
        $generator = new UlidGenerator($factory);

        self::assertSame($ulid, $generator->generate($em, new Entity()));
    }
}
