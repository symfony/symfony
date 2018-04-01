<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator;

/**
 * Prepares an object for validation.
 *
 * Concrete implementations of this interface are used by {@link ValidationVisitorInterface}
 * to initialize objects just before validating them.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ObjectInitializerInterface
{
    /**
     * Initializes an object just before validation.
     *
     * @param object $object The object to validate
     */
    public function initialize($object);
}
