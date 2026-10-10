<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateIntervalType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Tests\Fixtures\TypeInfoFormTypeGuesserCase;
use Symfony\Component\Form\TypeInfoFormTypeGuesser;

class TypeInfoFormTypeGuesserTest extends TestCase
{
    #[DataProvider('provideGuessTypeCases')]
    public function testGuessType(?TypeGuess $expected, string $class, string $property)
    {
        $this->assertEquals($expected, (new TypeInfoFormTypeGuesser())->guessType($class, $property));
    }

    public static function provideGuessTypeCases(): iterable
    {
        yield 'undefined class' => [null, 'UndefinedClass', 'property'];
        yield 'undefined property' => [null, TypeInfoFormTypeGuesserCase::class, 'undefined'];
        yield 'untyped' => [null, TypeInfoFormTypeGuesserCase::class, 'untyped'];
        yield 'mixed' => [null, TypeInfoFormTypeGuesserCase::class, 'mixed'];
        yield 'string' => [null, TypeInfoFormTypeGuesserCase::class, 'string'];
        yield 'union' => [null, TypeInfoFormTypeGuesserCase::class, 'union'];
        yield 'array' => [null, TypeInfoFormTypeGuesserCase::class, 'array'];
        yield 'enum' => [null, TypeInfoFormTypeGuesserCase::class, 'enum'];
        yield 'DateTimeImmutable subclass' => [null, TypeInfoFormTypeGuesserCase::class, 'dateTimeSubclass'];

        yield 'int' => [new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'int'];
        yield 'nullable int' => [new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'nullableInt'];
        yield 'private int' => [new TypeGuess(IntegerType::class, [], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'privateInt'];
        yield 'float' => [new TypeGuess(NumberType::class, [], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'float'];
        yield 'bool' => [new TypeGuess(CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'bool'];
        yield 'nullable bool' => [new TypeGuess(CheckboxType::class, [], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'nullableBool'];
        yield 'DateTimeInterface' => [new TypeGuess(DateTimeType::class, ['input' => 'datetime_immutable'], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'dateTimeInterface'];
        yield 'DateTime' => [new TypeGuess(DateTimeType::class, ['input' => 'datetime'], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'dateTime'];
        yield 'nullable DateTimeImmutable' => [new TypeGuess(DateTimeType::class, ['input' => 'datetime_immutable'], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'dateTimeImmutable'];
        yield 'DatePoint' => [new TypeGuess(DateTimeType::class, ['input' => 'date_point'], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'datePoint'];
        yield 'DateInterval' => [new TypeGuess(DateIntervalType::class, [], Guess::MEDIUM_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'dateInterval'];
    }

    #[DataProvider('provideGuessRequiredCases')]
    public function testGuessRequired(?ValueGuess $expected, string $class, string $property)
    {
        $this->assertEquals($expected, (new TypeInfoFormTypeGuesser())->guessRequired($class, $property));
    }

    public static function provideGuessRequiredCases(): iterable
    {
        yield 'undefined class' => [null, 'UndefinedClass', 'property'];
        yield 'undefined property' => [null, TypeInfoFormTypeGuesserCase::class, 'undefined'];
        yield 'untyped' => [null, TypeInfoFormTypeGuesserCase::class, 'untyped'];
        yield 'mixed' => [null, TypeInfoFormTypeGuesserCase::class, 'mixed'];
        yield 'string' => [null, TypeInfoFormTypeGuesserCase::class, 'string'];
        yield 'int' => [null, TypeInfoFormTypeGuesserCase::class, 'int'];
        yield 'DateTime' => [null, TypeInfoFormTypeGuesserCase::class, 'dateTime'];

        yield 'nullable string' => [new ValueGuess(false, Guess::LOW_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'nullableString'];
        yield 'nullable int' => [new ValueGuess(false, Guess::LOW_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'nullableInt'];
        yield 'nullable DateTimeImmutable' => [new ValueGuess(false, Guess::LOW_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'dateTimeImmutable'];
        yield 'bool' => [new ValueGuess(false, Guess::LOW_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'bool'];
        yield 'nullable bool' => [new ValueGuess(false, Guess::LOW_CONFIDENCE), TypeInfoFormTypeGuesserCase::class, 'nullableBool'];
    }
}
