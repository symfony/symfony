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
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     *
     * @return Guess\TypeGuess|null A guess for the field's type and options
     */
    public function guessType($class, $property);

    /**
     * Returns a guess whether a property of a class is required.
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     *
     * @return Guess\ValueGuess|null A guess for the field's required setting
     */
    public function guessRequired($class, $property);

    /**
     * Returns a guess about the field's maximum length.
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     *
     * @return Guess\ValueGuess|null A guess for the field's maximum length
     */
    public function guessMaxLength($class, $property);

    /**
     * Returns a guess about the field's pattern.
     *
     * - When you have a min value, you guess a min length of this min (LOW_CONFIDENCE)
     * - Then line below, if this value is a float type, this is wrong so you guess null with MEDIUM_CONFIDENCE to override the previous guess.
     * Example:
     *  You want a float greater than 5, 4.512313 is not valid but length(4.512314) > length(5)
     *
     * @see https://github.com/symfony/symfony/pull/3927
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     *
     * @return Guess\ValueGuess|null A guess for the field's required pattern
     */
    public function guessPattern($class, $property);
}
