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
class NoMatchingFactoryFoundException extends \InvalidArgumentException
{
    public function __construct($annotationClass)
    {
        parent::__construct(sprintf('No matching AnnotationAdapterFactory found for %s.', $annotationClass));
    }
}
