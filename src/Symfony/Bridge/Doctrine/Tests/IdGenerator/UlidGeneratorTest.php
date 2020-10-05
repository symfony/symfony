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
use Symfony\Bridge\Doctrine\IdGenerator\UlidGenerator;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\Ulid;

class UlidGeneratorTest extends TestCase
{
    public function testUlidCanBeGenerated()
    {
        $em = new EntityManager();
        $generator = new UlidGenerator();
        $ulid = $generator->generate($em, new Entity());

        $this->assertInstanceOf(AbstractUid::class, $ulid);
        $this->assertInstanceOf(Ulid::class, $ulid);
        $this->assertTrue(Ulid::isValid($ulid));
    }
}
