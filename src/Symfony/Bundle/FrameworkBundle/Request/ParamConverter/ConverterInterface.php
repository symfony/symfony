<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Request\ParamConverter;

use Symfony\Component\HttpFoundation\Request;

interface ConverterInterface
{
    /**
     * Convert the \ReflectionParameter to something else.
     *
     * @param Request              $request
     * @param \ReflectionParameter $property
     */
    function apply(Request $request, \ReflectionParameter $parameter);

    /**
     * Returns boolean true if the ReflectionClass is supported, false otherwise
     *
     * @param  \ReflectionParameter $parameter
     *
     * @return Boolean
     */
    function supports(\ReflectionClass $class);
}
