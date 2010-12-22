<?php

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
     * @return boolean
     */
    function supports(\ReflectionClass $class);
}
