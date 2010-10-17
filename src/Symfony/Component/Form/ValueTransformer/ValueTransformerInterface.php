<?php

namespace Symfony\Component\Form\ValueTransformer;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\Localizable;

/**
 * Transforms a value between different representations.
 *
 * @author     Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
interface ValueTransformerInterface extends Localizable
{
    /**
     * Transforms a value from the original representation to a transformed representation.
     *
     * This method is called on two occasions inside a form field:
     *
     * 1. When the form field is initialized with the data attached from the datasource (object or array).
     * 2. When data from a request is bound using {@link Field::bind()} to transform the new input data
     *    back into the renderable format. For example if you have a date field and bind '2009-10-10' onto
     *    it you might accept this value because its easily parsed, but the transformer still writes back
     *    "2009/10/10" onto the form field (for further displaying or other purposes).
     *
     * @param  mixed $value     The value in the original representation
     * @return mixed            The value in the transformed representation
     * @throws InvalidArgument  Exception when the argument is no string
     * @throws ValueTransformer Exception when the transformation fails
     */
    function transform($value);

    /**
     * Transforms a value from the transformed representation to its original
     * representation.
     *
     * This method must be able to deal with null values.
     *
     * This method is called when {@link Field::bind()} is called to transform the requests tainted data
     * into an acceptable format for your data processing/model layer.
     *
     * @param  mixed $value         The value in the transformed representation
     * @param  mixed $originalValue The original value from the datasource that is about to be overwritten by the new value.
     * @return mixed                The value in the original representation
     * @throws InvalidArgument      Exception when the argument is not of the
     *                              expected type
     * @throws ValueTransformer     Exception when the transformation fails
     */
    function reverseTransform($value, $originalValue);
}