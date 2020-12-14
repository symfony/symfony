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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Form\DoctrineOrmTypeGuesser;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class DoctrineOrmTypeGuesserTest extends TestCase
{
    /**
     * @dataProvider requiredType
     */
    public function testTypeGuesser(string $type, $expected)
    {
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->expects($this->once())->method('getTypeOfField')->with('field')->willReturn($type);

        $this->assertEquals($expected, $this->getGuesser($classMetadata)->guessType('TestEntity', 'field'));
    }

    public function requiredType()
    {
        yield [Types::DATE_IMMUTABLE, new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateType', ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE)];
        yield [Types::DATE_MUTABLE, new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateType', [], Guess::HIGH_CONFIDENCE)];

        yield [Types::TIME_IMMUTABLE, new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TimeType', ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE)];
        yield [Types::TIME_MUTABLE, new TypeGuess('Symfony\Component\Form\Extension\Core\Type\TimeType', [], Guess::HIGH_CONFIDENCE)];

        yield [Types::DATETIME_IMMUTABLE, new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateTimeType', ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE)];
        yield [Types::DATETIMETZ_IMMUTABLE, new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateTimeType', ['input' => 'datetime_immutable'], Guess::HIGH_CONFIDENCE)];
        yield [Types::DATETIME_MUTABLE, new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateTimeType', [], Guess::HIGH_CONFIDENCE)];
        yield [Types::DATETIMETZ_MUTABLE, new TypeGuess('Symfony\Component\Form\Extension\Core\Type\DateTimeType', [], Guess::HIGH_CONFIDENCE)];
    }

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
        $classMetadata->expects($this->once())->method('isNullable')->with('field')->willReturn(false);

        $return[] = [$classMetadata, new ValueGuess(true, Guess::HIGH_CONFIDENCE)];

        // Simple field, nullable
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->expects($this->once())->method('isNullable')->with('field')->willReturn(true);

        $return[] = [$classMetadata, new ValueGuess(false, Guess::MEDIUM_CONFIDENCE)];

        // One-to-one, nullable (by default)
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->expects($this->once())->method('isAssociationWithSingleJoinColumn')->with('field')->willReturn(true);

        $mapping = ['joinColumns' => [[]]];
        $classMetadata->expects($this->once())->method('getAssociationMapping')->with('field')->willReturn($mapping);

        $return[] = [$classMetadata, new ValueGuess(false, Guess::HIGH_CONFIDENCE)];

        // One-to-one, nullable (explicit)
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->expects($this->once())->method('isAssociationWithSingleJoinColumn')->with('field')->willReturn(true);

        $mapping = ['joinColumns' => [['nullable' => true]]];
        $classMetadata->expects($this->once())->method('getAssociationMapping')->with('field')->willReturn($mapping);

        $return[] = [$classMetadata, new ValueGuess(false, Guess::HIGH_CONFIDENCE)];

        // One-to-one, not nullable
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->expects($this->once())->method('isAssociationWithSingleJoinColumn')->with('field')->willReturn(true);

        $mapping = ['joinColumns' => [['nullable' => false]]];
        $classMetadata->expects($this->once())->method('getAssociationMapping')->with('field')->willReturn($mapping);

        $return[] = [$classMetadata, new ValueGuess(true, Guess::HIGH_CONFIDENCE)];

        // One-to-many, no clue
        $classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')->disableOriginalConstructor()->getMock();
        $classMetadata->expects($this->once())->method('isAssociationWithSingleJoinColumn')->with('field')->willReturn(false);

        $return[] = [$classMetadata, null];

        return $return;
    }

    private function getGuesser(ClassMetadata $classMetadata)
    {
        $em = $this->getMockBuilder(ObjectManager::class)->getMock();
        $em->expects($this->once())->method('getClassMetaData')->with('TestEntity')->willReturn($classMetadata);

        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $registry->expects($this->once())->method('getManagers')->willReturn([$em]);

        return new DoctrineOrmTypeGuesser($registry);
    }
}
