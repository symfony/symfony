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
use Symfony\Component\Form\EnumFormTypeGuesser;
use Symfony\Component\Form\Extension\Core\Type\EnumType as FormEnumType;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Tests\Fixtures\BackedEnumFormTypeGuesserCaseEnum;
use Symfony\Component\Form\Tests\Fixtures\EnumFormTypeGuesserCase;
use Symfony\Component\Form\Tests\Fixtures\EnumFormTypeGuesserCaseEnum;

class EnumFormTypeGuesserTest extends TestCase
{
    #[DataProvider('provideGuessTypeCases')]
    public function testGuessType(?TypeGuess $expectedTypeGuess, string $class, string $property)
    {
        $typeGuesser = new EnumFormTypeGuesser();

        $typeGuess = $typeGuesser->guessType($class, $property);

        self::assertEquals($expectedTypeGuess, $typeGuess);
    }

    #[DataProvider('provideGuessRequiredCases')]
    public function testGuessRequired(?ValueGuess $expectedValueGuess, string $class, string $property)
    {
        $typeGuesser = new EnumFormTypeGuesser();

        $valueGuess = $typeGuesser->guessRequired($class, $property);

        self::assertEquals($expectedValueGuess, $valueGuess);
    }

    public static function provideGuessTypeCases(): iterable
    {
        yield 'Undefined class' => [
            null,
            'UndefinedClass',
            'undefinedProperty',
        ];

        yield 'Undefined property' => [
            null,
            EnumFormTypeGuesserCase::class,
            'undefinedProperty',
        ];

        yield 'Undefined enum' => [
            null,
            EnumFormTypeGuesserCase::class,
            'undefinedEnum',
        ];

        yield 'Non-enum property' => [
            null,
            EnumFormTypeGuesserCase::class,
            'string',
        ];

        yield 'Enum property' => [
            new TypeGuess(
                FormEnumType::class,
                [
                    'class' => EnumFormTypeGuesserCaseEnum::class,
                ],
                Guess::HIGH_CONFIDENCE,
            ),
            EnumFormTypeGuesserCase::class,
            'enum',
        ];

        yield 'Nullable enum property' => [
            new TypeGuess(
                FormEnumType::class,
                [
                    'class' => EnumFormTypeGuesserCaseEnum::class,
                ],
                Guess::HIGH_CONFIDENCE,
            ),
            EnumFormTypeGuesserCase::class,
            'nullableEnum',
        ];

        yield 'Backed enum property' => [
            new TypeGuess(
                FormEnumType::class,
                [
                    'class' => BackedEnumFormTypeGuesserCaseEnum::class,
                ],
                Guess::HIGH_CONFIDENCE,
            ),
            EnumFormTypeGuesserCase::class,
            'backedEnum',
        ];

        yield 'Nullable backed enum property' => [
            new TypeGuess(
                FormEnumType::class,
                [
                    'class' => BackedEnumFormTypeGuesserCaseEnum::class,
                ],
                Guess::HIGH_CONFIDENCE,
            ),
            EnumFormTypeGuesserCase::class,
            'nullableBackedEnum',
        ];

        yield 'Enum union property' => [
            null,
            EnumFormTypeGuesserCase::class,
            'enumUnion',
        ];

        yield 'Enum intersection property' => [
            null,
            EnumFormTypeGuesserCase::class,
            'enumIntersection',
        ];
    }

    public static function provideGuessRequiredCases(): iterable
    {
        yield 'Unknown class' => [
            null,
            'UndefinedClass',
            'undefinedProperty',
        ];

        yield 'Unknown property' => [
            null,
            EnumFormTypeGuesserCase::class,
            'undefinedProperty',
        ];

        yield 'Undefined enum' => [
            null,
            EnumFormTypeGuesserCase::class,
            'undefinedEnum',
        ];

        yield 'Non-enum property' => [
            null,
            EnumFormTypeGuesserCase::class,
            'string',
        ];

        yield 'Enum property' => [
            new ValueGuess(
                true,
                Guess::HIGH_CONFIDENCE,
            ),
            EnumFormTypeGuesserCase::class,
            'enum',
        ];

        yield 'Nullable enum property' => [
            new ValueGuess(
                false,
                Guess::HIGH_CONFIDENCE,
            ),
            EnumFormTypeGuesserCase::class,
            'nullableEnum',
        ];

        yield 'Backed enum property' => [
            new ValueGuess(
                true,
                Guess::HIGH_CONFIDENCE,
            ),
            EnumFormTypeGuesserCase::class,
            'backedEnum',
        ];

        yield 'Nullable backed enum property' => [
            new ValueGuess(
                false,
                Guess::HIGH_CONFIDENCE,
            ),
            EnumFormTypeGuesserCase::class,
            'nullableBackedEnum',
        ];

        yield 'Enum union property' => [
            null,
            EnumFormTypeGuesserCase::class,
            'enumUnion',
        ];

        yield 'Enum intersection property' => [
            null,
            EnumFormTypeGuesserCase::class,
            'enumIntersection',
        ];
    }
}
