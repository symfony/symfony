<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Caster;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Test\VarDumperTestTrait;

/**
 * @requires function \Doctrine\Common\Collections\ArrayCollection::__construct
 */
class DoctrineCasterTest extends TestCase
{
    use VarDumperTestTrait;

    public function testCastPersistentCollection()
    {
        $classMetadata = new ClassMetadata(__CLASS__);

        $collection = new PersistentCollection($this->createMock(EntityManagerInterface::class), $classMetadata, new ArrayCollection(['test']));

        $expected = <<<EODUMP
Doctrine\ORM\PersistentCollection {
%A
  -em: Mock_EntityManagerInterface_%s { …3}
  -backRefFieldName: null
  -typeClass: Doctrine\ORM\Mapping\ClassMetadata { …}
%A
EODUMP;

        $this->assertDumpMatchesFormat($expected, $collection);
    }
}
