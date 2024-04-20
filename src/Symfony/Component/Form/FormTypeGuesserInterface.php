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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormTypeGuesserInterface
{
    /**
     * Returns a field guess for a property name of a class.
     */
    public function guessType(string $class, string $property): ?Guess\TypeGuess;

    /**
     * Returns a guess whether a property of a class is required.
     */
    public function guessRequired(string $class, string $property): ?Guess\ValueGuess;

    /**
     * Returns a guess about the field's maximum length.
     */
    public function guessMaxLength(string $class, string $property): ?Guess\ValueGuess;

    /**
     * Returns a guess about the field's pattern.
     */
    public function guessPattern(string $class, string $property): ?Guess\ValueGuess;
}
