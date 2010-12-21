<?php

namespace Symfony\Bundle\FrameworkBundle\ParamConverter\Converter;

use Symfony\Component\HttpFoundation\Request;

interface ConverterInterface
{
    /**
     * Convert the \ReflectionPropertt to something else.
     *
     * @param Request              $request
     * @param \ReflectionParameter $property
     */
    function apply(Request $request, \ReflectionParameter $parameter);

    /**
     * Returns boolean true if the ReflectionProperty is supported. Else false
     *
     * @param  \ReflectionParameter $parameter
     * @return boolean
     */
    function supports(\ReflectionClass $class);
}
