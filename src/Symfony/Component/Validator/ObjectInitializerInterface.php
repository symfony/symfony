<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * Interface for object initializers.
 *
 * Concrete implementations of this interface are used by the GraphWalker
 * to initialize objects just before validating them/
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface ObjectInitializerInterface
{
    /**
     * Initializes an object just before validation.
     *
     * @param object $object The object to validate
     *
     * @api
     */
    public function initialize($object);
}
