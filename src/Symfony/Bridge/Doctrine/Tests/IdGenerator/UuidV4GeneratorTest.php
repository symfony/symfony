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

use Doctrine\ORM\Mapping\Entity;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\IdGenerator\UuidV4Generator;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\UuidV4;

class UuidV4GeneratorTest extends TestCase
{
    public function testUuidv4CanBeGenerated()
    {
        $em = new EntityManager();
        $generator = new UuidV4Generator();

        $uuid = $generator->generate($em, new Entity());

        $this->assertInstanceOf(AbstractUid::class, $uuid);
        $this->assertInstanceOf(UuidV4::class, $uuid);
        $this->assertTrue(UuidV4::isValid($uuid));
    }
}
