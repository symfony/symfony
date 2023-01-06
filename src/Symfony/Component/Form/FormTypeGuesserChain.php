<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

class FormTypeGuesserChain implements FormTypeGuesserInterface
{
    private array $guessers = [];

    /**
     * @param FormTypeGuesserInterface[] $guessers
     *
     * @throws UnexpectedTypeException if any guesser does not implement FormTypeGuesserInterface
     */
    public function __construct(iterable $guessers)
    {
        $tmpGuessers = [];
        foreach ($guessers as $guesser) {
            if (!$guesser instanceof FormTypeGuesserInterface) {
                throw new UnexpectedTypeException($guesser, FormTypeGuesserInterface::class);
            }

            if ($guesser instanceof self) {
                $tmpGuessers[] = $guesser->guessers;
            } else {
                $tmpGuessers[] = [$guesser];
            }
        }

        $this->guessers = array_merge([], ...$tmpGuessers);
    }

    public function guessType(string $class, string $property): ?TypeGuess
    {
        return $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessType($class, $property);
        });
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        return $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessRequired($class, $property);
        });
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessMaxLength($class, $property);
        });
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return $this->guess(function ($guesser) use ($class, $property) {
            return $guesser->guessPattern($class, $property);
        });
    }

    /**
     * Executes a closure for each guesser and returns the best guess from the
     * return values.
     *
     * @param \Closure $closure The closure to execute. Accepts a guesser
     *                          as argument and should return a Guess instance
     */
    private function guess(\Closure $closure): ?Guess
    {
        $guesses = [];

        foreach ($this->guessers as $guesser) {
            if ($guess = $closure($guesser)) {
                $guesses[] = $guess;
            }
        }

        return Guess::getBestGuess($guesses);
    }
}
