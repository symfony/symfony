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
 * Property accessor adder configuration annotation.
 *
 * @Annotation
 * @Target({"METHOD"})
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class AdderAccessor
{
    /**
     * Associates this method to the adder of this property.
     *
     * @var string
     */
    public $property;
}
