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
     *
     * @return Guess\TypeGuess|null
     */
    public function guessType(string $class, string $property);

    /**
     * Returns a guess whether a property of a class is required.
     *
     * @return Guess\ValueGuess|null
     */
    public function guessRequired(string $class, string $property);

    /**
     * Returns a guess about the field's maximum length.
     *
     * @return Guess\ValueGuess|null
     */
    public function guessMaxLength(string $class, string $property);

    /**
     * Returns a guess about the field's pattern.
     *
     * @return Guess\ValueGuess|null
     */
    public function guessPattern(string $class, string $property);
}
