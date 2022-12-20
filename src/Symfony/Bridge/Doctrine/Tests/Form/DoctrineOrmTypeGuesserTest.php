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
        $classMetadata = self::createMock(ClassMetadata::class);
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->expects(self::once())->method('getTypeOfField')->with('field')->willReturn($type);

        self::assertEquals($expected, $this->getGuesser($classMetadata)->guessType('TestEntity', 'field'));
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
        self::assertEquals($expected, $this->getGuesser($classMetadata)->guessRequired('TestEntity', 'field'));
    }

    public function requiredProvider()
    {
        $return = [];

        // Simple field, not nullable
        $classMetadata = self::createMock(ClassMetadata::class);
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->expects(self::once())->method('isNullable')->with('field')->willReturn(false);

        $return[] = [$classMetadata, new ValueGuess(true, Guess::HIGH_CONFIDENCE)];

        // Simple field, nullable
        $classMetadata = self::createMock(ClassMetadata::class);
        $classMetadata->fieldMappings['field'] = true;
        $classMetadata->expects(self::once())->method('isNullable')->with('field')->willReturn(true);

        $return[] = [$classMetadata, new ValueGuess(false, Guess::MEDIUM_CONFIDENCE)];

        // One-to-one, nullable (by default)
        $classMetadata = self::createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())->method('isAssociationWithSingleJoinColumn')->with('field')->willReturn(true);

        $mapping = ['joinColumns' => [[]]];
        $classMetadata->expects(self::once())->method('getAssociationMapping')->with('field')->willReturn($mapping);

        $return[] = [$classMetadata, new ValueGuess(false, Guess::HIGH_CONFIDENCE)];

        // One-to-one, nullable (explicit)
        $classMetadata = self::createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())->method('isAssociationWithSingleJoinColumn')->with('field')->willReturn(true);

        $mapping = ['joinColumns' => [['nullable' => true]]];
        $classMetadata->expects(self::once())->method('getAssociationMapping')->with('field')->willReturn($mapping);

        $return[] = [$classMetadata, new ValueGuess(false, Guess::HIGH_CONFIDENCE)];

        // One-to-one, not nullable
        $classMetadata = self::createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())->method('isAssociationWithSingleJoinColumn')->with('field')->willReturn(true);

        $mapping = ['joinColumns' => [['nullable' => false]]];
        $classMetadata->expects(self::once())->method('getAssociationMapping')->with('field')->willReturn($mapping);

        $return[] = [$classMetadata, new ValueGuess(true, Guess::HIGH_CONFIDENCE)];

        // One-to-many, no clue
        $classMetadata = self::createMock(ClassMetadata::class);
        $classMetadata->expects(self::once())->method('isAssociationWithSingleJoinColumn')->with('field')->willReturn(false);

        $return[] = [$classMetadata, null];

        return $return;
    }

    private function getGuesser(ClassMetadata $classMetadata)
    {
        $em = self::createMock(ObjectManager::class);
        $em->expects(self::once())->method('getClassMetaData')->with('TestEntity')->willReturn($classMetadata);

        $registry = self::createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagers')->willReturn([$em]);

        return new DoctrineOrmTypeGuesser($registry);
    }
}
