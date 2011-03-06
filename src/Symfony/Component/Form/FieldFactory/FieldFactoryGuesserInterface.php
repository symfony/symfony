<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\FieldFactory;

/**
 * Guesses field classes and options for the properties of a class
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface FieldFactoryGuesserInterface
{
    /**
     * Returns a field guess for a property name of a class
     *
     * @param  string $class           The fully qualified class name
     * @param  string $property        The name of the property to guess for
     * @return FieldFactoryClassGuess  A guess for the field's class and options
     */
    function guessClass($class, $property);

    /**
     * Returns a guess whether a property of a class is required
     *
     * @param  string $class      The fully qualified class name
     * @param  string $property   The name of the property to guess for
     * @return FieldFactoryGuess  A guess for the field's required setting
     */
    function guessRequired($class, $property);

    /**
     * Returns a guess about the field's maximum length
     *
     * @param  string $class      The fully qualified class name
     * @param  string $property   The name of the property to guess for
     * @return FieldFactoryGuess  A guess for the field's maximum length
     */
    function guessMaxLength($class, $property);
}
