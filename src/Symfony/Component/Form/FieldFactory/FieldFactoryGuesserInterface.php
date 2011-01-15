<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\FieldFactory;

/**
 * Guesses field classes and options for the properties of an object
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface FieldFactoryGuesserInterface
{
    /**
     * Returns a field guess for a given property name
     *
     * @param  object $object          The object to guess for
     * @param  string $property        The name of the property to guess for
     * @return FieldFactoryClassGuess  A guess for the field's class and options
     */
    function guessClass($object, $property);

    /**
     * Returns a guess whether the given property is required
     *
     * @param  object $object     The object to guess for
     * @param  string $property   The name of the property to guess for
     * @return FieldFactoryGuess  A guess for the field's required setting
     */
    function guessRequired($object, $property);

    /**
     * Returns a guess about the field's maximum length
     *
     * @param  object $object     The object to guess for
     * @param  string $property   The name of the property to guess for
     * @return FieldFactoryGuess  A guess for the field's maximum length
     */
    function guessMaxLength($object, $property);
}
