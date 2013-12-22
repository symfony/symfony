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
     * Returns an array of guessed options
     *
     * @param string $class    The fully qualified class name
     * @param string $property The name of the property to guess for
     * @param string $type     Boh.
     *
     * @return array An array of guesses for the field's option
     */
    public function guessOptions($class, $property, $type);
}
