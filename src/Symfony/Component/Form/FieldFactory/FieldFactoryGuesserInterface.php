<?php

namespace Symfony\Component\Form\FieldFactory;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
