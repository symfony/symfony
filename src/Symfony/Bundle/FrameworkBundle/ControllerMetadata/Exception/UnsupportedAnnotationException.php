<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Exception;

/**
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
class UnsupportedAnnotationException extends \InvalidArgumentException
{
    public function __construct($factoryClass, $annotationClass)
    {
        parent::__construct(sprintf('%s only accepts %s annotations.', $factoryClass, $annotationClass));
    }
}
