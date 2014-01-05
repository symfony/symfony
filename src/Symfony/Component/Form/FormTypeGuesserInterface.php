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
     * Returns a field guess for a property name of a class
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     *
     * @return Guess\TypeGuess|null A guess for the field's type and options
     */
    public function guessType($class, $property);

    /**
     * Returns an array of guessed attributes
     *
     * @param string                    $class    The fully qualified class name
     * @param string                    $property The name of the property to guess for
     * @param ResolvedFormTypeInterface $type     Field's type
     *
     * @return Guess\ValueGuess[] An array of guesses for the field's attributes
     */
    public function guessAttributes($class, $property);

    /**
     * Returns a guess whether a property of a class is required
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     *
     * @return Guess\ValueGuess A guess for the field's required setting
     */
    public function guessRequired($class, $property);
}
