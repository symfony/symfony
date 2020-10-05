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
use Symfony\Bridge\Doctrine\IdGenerator\UuidV6Generator;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\UuidV6;

class UuidV6GeneratorTest extends TestCase
{
    public function testUuidv6CanBeGenerated()
    {
        $em = new EntityManager();
        $generator = new UuidV6Generator();

        $uuid = $generator->generate($em, new Entity());

        $this->assertInstanceOf(AbstractUid::class, $uuid);
        $this->assertInstanceOf(UuidV6::class, $uuid);
        $this->assertTrue(UuidV6::isValid($uuid));
    }
}
