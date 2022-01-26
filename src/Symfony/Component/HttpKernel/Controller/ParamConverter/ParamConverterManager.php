<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ParamConverter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ParamConverter;

/**
 * Managers converters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Henrik Bjornskov <henrik@bjrnskov.dk>
 */
class ParamConverterManager
{
    /**
     * @var array
     */
    private $converters = [];

    /**
     * @var array
     */
    private $namedConverters = [];

    /**
     * Applies all converters to the passed configurations and stops when a
     * converter is applied it will move on to the next configuration and so on.
     *
     * @param array|object $configurations
     */
    public function apply(Request $request, $configurations)
    {
        if (\is_object($configurations)) {
            $configurations = [$configurations];
        }

        foreach ($configurations as $configuration) {
            $this->applyConverter($request, $configuration);
        }
    }

    /**
     * Applies converter on request based on the given configuration.
     */
    private function applyConverter(Request $request, ParamConverter $configuration)
    {
        $value = $request->attributes->get($configuration->getName());
        $className = $configuration->getClass();

        // If the value is already an instance of the class we are trying to convert it into
        // we should continue as no conversion is required
        if (\is_object($value) && $value instanceof $className) {
            return;
        }

        if ($converterName = $configuration->getConverter()) {
            if (!isset($this->namedConverters[$converterName])) {
                throw new \RuntimeException(sprintf("No converter named '%s' found for conversion of parameter '%s'.", $converterName, $configuration->getName()));
            }

            $converter = $this->namedConverters[$converterName];

            if (!$converter->supports($configuration)) {
                throw new \RuntimeException(sprintf("Converter '%s' does not support conversion of parameter '%s'.", $converterName, $configuration->getName()));
            }

            $converter->apply($request, $configuration);

            return;
        }

        foreach ($this->all() as $converter) {
            if ($converter->supports($configuration)) {
                if ($converter->apply($request, $configuration)) {
                    return;
                }
            }
        }
    }

    /**
     * Adds a parameter converter.
     *
     * Converters match either explicitly via $name or by iteration over all
     * converters with a $priority. If you pass a $priority = null then the
     * added converter will not be part of the iteration chain and can only
     * be invoked explicitly.
     *
     * @param int    $priority the priority (between -10 and 10)
     * @param string $name     name of the converter
     */
    public function add(ParamConverterInterface $converter, $priority = 0, $name = null)
    {
        if (null !== $priority) {
            if (!isset($this->converters[$priority])) {
                $this->converters[$priority] = [];
            }

            $this->converters[$priority][] = $converter;
        }

        if (null !== $name) {
            $this->namedConverters[$name] = $converter;
        }
    }

    /**
     * Returns all registered param converters.
     *
     * @return array An array of param converters
     */
    public function all()
    {
        krsort($this->converters);

        $converters = [];
        foreach ($this->converters as $all) {
            $converters = array_merge($converters, $all);
        }

        return $converters;
    }
}
