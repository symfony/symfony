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
use Symfony\Bridge\Doctrine\IdGenerator\UuidV1Generator;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\UuidV1;

class UuidV1GeneratorTest extends TestCase
{
    public function testUuidv1CanBeGenerated()
    {
        $em = new EntityManager();
        $generator = new UuidV1Generator();

        $uuid = $generator->generate($em, new Entity());

        $this->assertInstanceOf(AbstractUid::class, $uuid);
        $this->assertInstanceOf(UuidV1::class, $uuid);
        $this->assertTrue(UuidV1::isValid($uuid));
    }
}
