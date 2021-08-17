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

use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms a value between different representations.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface DataTransformerInterface
{
    /**
     * Transforms a value from the original representation to a transformed representation.
     *
     * This method is called when the form field is initialized with its default data, on
     * two occasions for two types of transformers:
     *
     * 1. Model transformers which normalize the model data.
     *    This is mainly useful when the same form type (the same configuration)
     *    has to handle different kind of underlying data, e.g The DateType can
     *    deal with strings or \DateTime objects as input.
     *
     * 2. View transformers which adapt the normalized data to the view format.
     *    a/ When the form is simple, the value returned by convention is used
     *       directly in the view and thus can only be a string or an array. In
     *       this case the data class should be null.
     *
     *    b/ When the form is compound the returned value should be an array or
     *       an object to be mapped to the children. Each property of the compound
     *       data will be used as model data by each child and will be transformed
     *       too. In this case data class should be the class of the object, or null
     *       when it is an array.
     *
     * All transformers are called in a configured order from model data to view value.
     * At the end of this chain the view data will be validated against the data class
     * setting.
     *
     * This method must be able to deal with empty values. Usually this will
     * be NULL, but depending on your implementation other empty values are
     * possible as well (such as empty strings). The reasoning behind this is
     * that data transformers must be chainable. If the transform() method
     * of the first data transformer outputs NULL, the second must be able to
     * process that value.
     *
     * @param mixed $value The value in the original representation
     *
     * @return mixed
     *
     * @throws TransformationFailedException when the transformation fails
     */
    public function transform(mixed $value);

    /**
     * Transforms a value from the transformed representation to its original
     * representation.
     *
     * This method is called when {@link Form::submit()} is called to transform the requests tainted data
     * into an acceptable format.
     *
     * The same transformers are called in the reverse order so the responsibility is to
     * return one of the types that would be expected as input of transform().
     *
     * This method must be able to deal with empty values. Usually this will
     * be an empty string, but depending on your implementation other empty
     * values are possible as well (such as NULL). The reasoning behind
     * this is that value transformers must be chainable. If the
     * reverseTransform() method of the first value transformer outputs an
     * empty string, the second value transformer must be able to process that
     * value.
     *
     * By convention, reverseTransform() should return NULL if an empty string
     * is passed.
     *
     * @param mixed $value The value in the transformed representation
     *
     * @return mixed
     *
     * @throws TransformationFailedException when the transformation fails
     */
    public function reverseTransform(mixed $value);
}
