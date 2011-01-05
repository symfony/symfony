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
 * Automatically creates form fields for properties of an object
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface FieldFactoryInterface
{
    /**
     * Returns a field for a given property name
     *
     * @param  object $object     The object to create a field for
     * @param  string $property   The name of the property
     * @param  array $options     Custom options for creating the field
     * @return FieldInterface     A field instance
     */
    function getInstance($object, $property, array $options = array());
}
