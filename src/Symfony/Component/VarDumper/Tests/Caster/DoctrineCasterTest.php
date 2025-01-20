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

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManagerClass = $entityManager::class;
        $collection = new PersistentCollection($entityManager, $classMetadata, new ArrayCollection(['test']));

        if (property_exists(PersistentCollection::class, 'isDirty')) {
            // Collections >= 2
            $expected = <<<EODUMP
                Doctrine\ORM\PersistentCollection {
                %A
                  -backRefFieldName: null
                  -isDirty: false
                  -em: $entityManagerClass { …3}
                  -typeClass: Doctrine\ORM\Mapping\ClassMetadata { …}
                %A
                EODUMP;
        } else {
            // Collections 1
            $expected = <<<EODUMP
                Doctrine\ORM\PersistentCollection {
                %A
                  -em: $entityManagerClass { …3}
                  -backRefFieldName: null
                  -typeClass: Doctrine\ORM\Mapping\ClassMetadata { …}
                %A
                EODUMP;
        }

        $this->assertDumpMatchesFormat($expected, $collection);
    }
}
