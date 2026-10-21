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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class FormTypeGuesserChainTest extends TestCase
{
    public function testGuessesAreComputedOncePerClassAndProperty()
    {
        $guesser = new class implements FormTypeGuesserInterface {
            public array $calls = [];

            public function guessType(string $class, string $property): ?TypeGuess
            {
                $this->calls[] = [__FUNCTION__, $class, $property];

                return new TypeGuess(TextType::class, [], Guess::HIGH_CONFIDENCE);
            }

            public function guessRequired(string $class, string $property): ?ValueGuess
            {
                $this->calls[] = [__FUNCTION__, $class, $property];

                return new ValueGuess(true, Guess::HIGH_CONFIDENCE);
            }

            public function guessMaxLength(string $class, string $property): ?ValueGuess
            {
                $this->calls[] = [__FUNCTION__, $class, $property];

                return null;
            }

            public function guessPattern(string $class, string $property): ?ValueGuess
            {
                $this->calls[] = [__FUNCTION__, $class, $property];

                return null;
            }
        };

        $chain = new FormTypeGuesserChain([$guesser]);

        $guess = static fn () => [
            $chain->guessType(\stdClass::class, 'foo'),
            $chain->guessRequired(\stdClass::class, 'foo'),
            $chain->guessMaxLength(\stdClass::class, 'foo'),
            $chain->guessPattern(\stdClass::class, 'foo'),
        ];

        $firstGuesses = $guess();
        $secondGuesses = $guess();
        $chain->guessType(\stdClass::class, 'bar');
        $chain->guessType(\ArrayObject::class, 'foo');

        $this->assertSame([
            ['guessType', \stdClass::class, 'foo'],
            ['guessRequired', \stdClass::class, 'foo'],
            ['guessMaxLength', \stdClass::class, 'foo'],
            ['guessPattern', \stdClass::class, 'foo'],
            ['guessType', \stdClass::class, 'bar'],
            ['guessType', \ArrayObject::class, 'foo'],
        ], $guesser->calls);
        $this->assertEquals([new TypeGuess(TextType::class, [], Guess::HIGH_CONFIDENCE), new ValueGuess(true, Guess::HIGH_CONFIDENCE), null, null], $firstGuesses);
        $this->assertSame($firstGuesses, $secondGuesses);
    }
}
