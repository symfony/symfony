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

use Symfony\Bundle\FrameworkBundle\Request\ParamConverter\ConverterInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Keeps track of param converters.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Henrik Bjornskov <hb@peytz.dk>
 */
class ConverterManager
{
    /**
     * @var array
     */
    protected $converters = array();

    /**
     * Cycles through all converters and if a converter supports the class it applies
     * the converter. If no converter matches the ReflectionParameters::getClass() value
     * a InvalidArgumentException is thrown.
     *
     * @param  Request $request
     * @param  array   $reflectionParam An array of ReflectionParameter objects
     *
     * @throws InvalidArgumentException
     */
    public function apply(Request $request, \ReflectionParameter $reflectionParam)
    {
        $converted = false;
        $converters = $this->all();
        $reflectionClass = $reflectionParam->getClass();

        foreach ($this->all() as $converter) {
            if ($converter->supports($reflectionClass)) {
                $converter->apply($request, $reflectionParam);
                $converted = true;
                break;
            }
        }

        if (true !== $converted) {
            throw new \InvalidArgumentException(sprintf('Could not convert attribute "%s" into an instance of "%s"', $reflectionParam->getName(), $reflectionClass->getName()));
        }
    }

    /**
     * Add a converter
     *
     * @param ConverterInterface $converter
     * @param integer            $prioriry = 0
     */
    public function add(ConverterInterface $converter, $priority = 0)
    {
        if (!isset($this->converters[$priority])) {
            $this->converters[$priority] = array();
        }

        $this->converters[$priority][] = $converter;
    }

    /**
     * Returns all converters sorted after their priorities
     *
     * @return array
     */
    public function all()
    {
        $all = $this->converters;
        $converters = array();
        krsort($this->converters);

        foreach ($all as $c) {
            $converters = array_merge($converters, $c);
        }

        return $converters;
    }
}
