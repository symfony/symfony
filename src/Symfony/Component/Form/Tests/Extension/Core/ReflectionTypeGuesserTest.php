<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\ReflectionTypeGuesser;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UuidType;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;
use Symfony\Component\Form\Tests\Fixtures\Foo;
use Symfony\Component\Form\Tests\Fixtures\Suit;
use Symfony\Component\Uid\Uuid;

class ReflectionTypeGuesserTest extends TestCase
{
    /**
     * @dataProvider guessTypeProvider
     */
    public function testGuessType(string $property, $expected)
    {
        $guesser = new ReflectionTypeGuesser();

        $this->assertEquals($expected, $guesser->guessType(ReflectionTypeGuesserTest_TestClass::class, $property));
    }

    public function guessTypeProvider(): array
    {
        return [
            ['uuid', new TypeGuess(UuidType::class, [], Guess::MEDIUM_CONFIDENCE)],
            ['string', new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE)],
            ['nullable', new TypeGuess(TextType::class, [], Guess::LOW_CONFIDENCE)],
            ['suit', new TypeGuess(EnumType::class, ['class' => Suit::class], Guess::MEDIUM_CONFIDENCE)],
            ['date', new TypeGuess(DateTimeType::class, ['input' => 'datetime_immutable'], Guess::LOW_CONFIDENCE)],
            ['foo', null],
            ['untyped', null],
        ];
    }

    /**
     * @dataProvider guessRequiredProvider
     */
    public function testGuessRequired(string $property, $expected)
    {
        $guesser = new ReflectionTypeGuesser();

        $this->assertEquals($expected, $guesser->guessRequired(ReflectionTypeGuesserTest_TestClass::class, $property));
    }

    public function guessRequiredProvider(): array
    {
        return [
            ['string', new ValueGuess(true, Guess::MEDIUM_CONFIDENCE)],
            ['nullable', new ValueGuess(false, Guess::MEDIUM_CONFIDENCE)],
            ['suit', new ValueGuess(true, Guess::MEDIUM_CONFIDENCE)],
            ['foo', new ValueGuess(true, Guess::MEDIUM_CONFIDENCE)],
            ['bool', new ValueGuess(false, Guess::MEDIUM_CONFIDENCE)],
            ['untyped', null],
        ];
    }
}

class ReflectionTypeGuesserTest_TestClass
{
    private Uuid $uuid;

    private string $string;

    private ?string $nullable;

    private Suit $suit;

    private \DateTimeImmutable $date;

    private Foo $foo;

    private bool $bool;

    private $untyped;
}
