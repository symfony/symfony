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

interface FormTypeGuesserInterface
{
    /**
     * Returns a field guess for a property name of a class
     *
     * @param string $class     The fully qualified class name
     * @param string $property  The name of the property to guess for
     *
     * @return TypeGuess A guess for the field's type and options
     */
    function guessType($class, $property);

    /**
     * Returns a guess whether a property of a class is required
     *
     * @param string $class     The fully qualified class name
     * @param string $property  The name of the property to guess for
     *
     * @return Guess  A guess for the field's required setting
     */
    function guessRequired($class, $property);

    /**
     * Returns a guess about the field's maximum length
     *
     * @param string $class     The fully qualified class name
     * @param string $property  The name of the property to guess for
     *
     * @return Guess  A guess for the field's maximum length
     */
    function guessMaxLength($class, $property);

    /**
     * Returns a guess about the field's minimum length
     *
     * @param  string $class      The fully qualified class name
     * @param  string $property   The name of the property to guess for
     *
     * @return Guess  A guess for the field's minimum length
     */
    function guessMinLength($class, $property);

}
