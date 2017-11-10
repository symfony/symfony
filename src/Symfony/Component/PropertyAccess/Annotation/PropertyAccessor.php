<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Annotation;

/**
 * Property accessor configuration annotation.
 *
 * @Annotation
 * @Target({"PROPERTY"})
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class PropertyAccessor
{
    /**
     * Custom setter method for the property.
     *
     * @var string
     */
    public $setter;

    /**
     * Custom getter method for the property.
     *
     * @var string
     */
    public $getter;

    /**
     * Custom adder method for the property.
     *
     * @var string
     */
    public $adder;

    /**
     * Custom remover method for the property.
     *
     * @var string
     */
    public $remover;
}
