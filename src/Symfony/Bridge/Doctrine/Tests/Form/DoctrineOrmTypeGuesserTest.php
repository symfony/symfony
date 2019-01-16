<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Form;

use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmTypeGuesser;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\ValueGuess;

class DoctrineOrmTypeGuesserTest extends TestCase
{
    /**
     * @dataProvider requiredProvider
     */
    public function testRequiredGuesser($classMetadata, $expected)
    {
        $this->assertEquals($expected, $this->getGuesser($classMetadata)->guessRequired('TestEntity', 'field'));
    }

    public function requiredProvider()
    {
        $return = [];

        // Simple field, not nullable
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->expects($this->once())->method('isNullable')->with('field')->will($this->returnValue(false));

        $return[] = [$classMetadata, new ValueGuess(true, Guess::HIGH_CONFIDENCE)];

        // Simple field, nullable
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->expects($this->once())->method('isNullable')->with('field')->will($this->returnValue(true));

        $return[] = [$classMetadata, new ValueGuess(false, Guess::MEDIUM_CONFIDENCE)];

        // One-to-one, nullable (by default)
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->expects($this->once())->method('isAssociationWithSingleJoinColumn')->with('field')->will($this->returnValue(true));

        $mapping = ['joinColumns' => [[]]];
        $classMetadata->expects($this->once())->method('getAssociationMapping')->with('field')->will($this->returnValue($mapping));

        $return[] = [$classMetadata, new ValueGuess(false, Guess::HIGH_CONFIDENCE)];

        // One-to-one, nullable (explicit)
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->expects($this->once())->method('isAssociationWithSingleJoinColumn')->with('field')->will($this->returnValue(true));

        $mapping = ['joinColumns' => [['nullable' => true]]];
        $classMetadata->expects($this->once())->method('getAssociationMapping')->with('field')->will($this->returnValue($mapping));

        $return[] = [$classMetadata, new ValueGuess(false, Guess::HIGH_CONFIDENCE)];

        // One-to-one, not nullable
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->expects($this->once())->method('isAssociationWithSingleJoinColumn')->with('field')->will($this->returnValue(true));

        $mapping = ['joinColumns' => [['nullable' => false]]];
        $classMetadata->expects($this->once())->method('getAssociationMapping')->with('field')->will($this->returnValue($mapping));

        $return[] = [$classMetadata, new ValueGuess(true, Guess::HIGH_CONFIDENCE)];

        // One-to-many, no clue
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->expects($this->once())->method('isAssociationWithSingleJoinColumn')->with('field')->will($this->returnValue(false));

        $return[] = [$classMetadata, null];

        return $return;
    }

    private function getGuesser(ClassMetadata $classMetadata)
    {
        $em = $this->getMockBuilder('Doctrine\Common\Persistence\ObjectManager')->getMock();
        $em->expects($this->once())->method('getClassMetaData')->with('TestEntity')->will($this->returnValue($classMetadata));

        $registry = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')->getMock();
        $registry->expects($this->once())->method('getManagers')->will($this->returnValue([$em]));

        return new DoctrineOrmTypeGuesser($registry);
    }
}
