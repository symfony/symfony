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
 * Automatically creates form fields for properties of a class
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
interface FieldFactoryInterface
{
    /**
     * Returns a field for a given property name of a class
     *
     * @param  string $class      The fully qualified class name
     * @param  string $property   The name of the property
     * @param  array $options     Custom options for creating the field
     * @return FieldInterface     A field instance
     */
    function getInstance($class, $property, array $options = array());
}
