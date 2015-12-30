<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\ParameterBag;

use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ParameterCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Implementation of {@link ParameterResolverInterface}.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Guilhem N. <egetick@gmail.com>
 */
trait ParameterResolverTrait
{
    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @param mixed $value A value
     *
     * @return mixed The resolved value
     *
     * @throws ParameterNotFoundException          if a placeholder references a parameter that does not exist
     * @throws ParameterCircularReferenceException if a circular reference is detected
     * @throws RuntimeException                    when a given parameter has a type problem.
     */
    public function resolveValue($value, array $resolving = array())
    {
        if (is_array($value)) {
            $args = array();
            foreach ($value as $k => $v) {
                $args[$this->resolveValue($k, $resolving)] = $this->resolveValue($v, $resolving);
            }

            return $args;
        }

        if (!is_string($value)) {
            return $value;
        }

        // we do this to deal with non string values (Boolean, integer, ...)
        // as the preg_replace_callback throw an exception when trying
        // a non-string in a parameter value
        if (preg_match('/^%([^%\s]+)%$/', $value, $match)) {
            $key = $match[1];
            $lcKey = strtolower($key);

            if (isset($resolving[$lcKey])) {
                throw new ParameterCircularReferenceException(array_keys($resolving));
            }

            $resolving[$lcKey] = true;

            return $this->getParameter($key, $resolving);
        }

        return preg_replace_callback('/%%|%([^%\s]+)%/', function ($match) use ($resolving, $value) {
            // skip %%
            if (!isset($match[1])) {
                return '%%';
            }

            $key = $match[1];
            $lcKey = strtolower($key);
            if (isset($resolving[$lcKey])) {
                throw new ParameterCircularReferenceException(array_keys($resolving));
            }

            $resolving[$lcKey] = true;
            $resolved = $this->getParameter($key, $resolving);

            if (!is_string($resolved) && !is_numeric($resolved)) {
                throw new RuntimeException(sprintf('A string value must be composed of strings and/or numbers, but found parameter "%s" of type %s inside string value "%s".', $key, gettype($resolved), $value));
            }

            return (string) $resolved;
        }, $value);
    }

    /**
     * Gets a parameter.
     *
     * @param string $name      The parameter name
     * @param array  $resolving used internally to detect circular references
     *
     * @return mixed The parameter value
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    abstract protected function getParameter($name, $resolving = array());
}
