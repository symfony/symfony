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
     * Transforms a value from the original representation to a transformed
     * representation.
     *
     * @param  mixed $value     The value in the original representation
     * @return mixed            The value in the transformed representation
     * @throws InvalidArgument  Exception when the argument is no string
     * @throws ValueTransformer Exception when the transformation fails
     */
    public function transform($value);

    /**
     * Transforms a value from the transformed representation to its original
     * representation.
     *
     * This method must be able to deal with null values.
     *
     * @param  mixed $value     The value in the transformed representation
     * @return mixed            The value in the original representation
     * @throws InvalidArgument  Exception when the argument is not of the
     *                          expected type
     * @throws ValueTransformer Exception when the transformation fails
     */
    public function reverseTransform($value);
}