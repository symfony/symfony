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
 * Holds parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParameterBag implements ParameterBagInterface
{
    use ParameterResolverTrait;

    protected $parameters = array();
    protected $resolved = false;

    /**
     * @param array $parameters An array of parameters
     */
    public function __construct(array $parameters = array())
    {
        $this->add($parameters);
    }

    /**
     * Clears all parameters.
     */
    public function clear()
    {
        $this->parameters = array();
    }

    /**
     * Adds parameters to the service container parameters.
     *
     * @param array $parameters An array of parameters
     */
    public function add(array $parameters)
    {
        foreach ($parameters as $key => $value) {
            $this->parameters[strtolower($key)] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        $name = strtolower($name);

        if (!array_key_exists($name, $this->parameters)) {
            if (!$name) {
                throw new ParameterNotFoundException($name);
            }

            $alternatives = array();
            foreach ($this->parameters as $key => $parameterValue) {
                $lev = levenshtein($name, $key);
                if ($lev <= strlen($name) / 3 || false !== strpos($key, $name)) {
                    $alternatives[] = $key;
                }
            }

            $nonNestedAlternative = null;
            if (!count($alternatives) && false !== strpos($name, '.')) {
                $namePartsLength = array_map('strlen', explode('.', $name));
                $key = substr($name, 0, -1 * (1 + array_pop($namePartsLength)));
                while (count($namePartsLength)) {
                    if ($this->has($key)) {
                        if (is_array($this->get($key))) {
                            $nonNestedAlternative = $key;
                        }
                        break;
                    }

                    $key = substr($key, 0, -1 * (1 + array_pop($namePartsLength)));
                }
            }

            throw new ParameterNotFoundException($name, null, null, null, $alternatives, $nonNestedAlternative);
        }

        return $this->parameters[$name];
    }

    /**
     * Sets a service container parameter.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     */
    public function set($name, $value)
    {
        $this->parameters[strtolower($name)] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return array_key_exists(strtolower($name), $this->parameters);
    }

    /**
     * Removes a parameter.
     *
     * @param string $name The parameter name
     */
    public function remove($name)
    {
        unset($this->parameters[strtolower($name)]);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve()
    {
        if ($this->resolved) {
            return;
        }

        $parameters = array();
        foreach ($this->parameters as $key => $value) {
            try {
                $value = $this->resolveValue($value);
                $parameters[$key] = $this->unescapeValue($value);
            } catch (ParameterNotFoundException $e) {
                $e->setSourceKey($key);

                throw $e;
            }
        }

        $this->parameters = $parameters;
        $this->resolved = true;
    }

    /**
     * Resolves parameters inside a string.
     *
     * @param string $value     The string to resolve
     * @param array  $resolving An array of keys that are being resolved (used internally to detect circular references)
     *
     * @return string The resolved string
     *
     * @throws ParameterNotFoundException          if a placeholder references a parameter that does not exist
     * @throws ParameterCircularReferenceException if a circular reference if detected
     * @throws RuntimeException                    when a given parameter has a type problem.
     *
     * @deprecated since 3.2, to be removed in 4.0. Use {@link static::resolveValue()} instead.
     */
    public function resolveString($value, array $resolving = array())
    {
        @trigger_error(sprintf('The %s method is deprecated since version 3.2 and will be removed in 4.0. Use %s::resolveValue() instead.', __METHOD__, __CLASS__), E_USER_DEPRECATED);

        return $this->resolveValue($value, $resolving);
    }

    /**
     * Whether the parameters are resolved or not (%parameter% replaced).
     *
     * @return bool
     */
    public function isResolved()
    {
        return $this->resolved;
    }

    /**
     * {@inheritdoc}
     */
    public function escapeValue($value)
    {
        if (is_string($value)) {
            return str_replace('%', '%%', $value);
        }

        if (is_array($value)) {
            $result = array();
            foreach ($value as $k => $v) {
                $result[$k] = $this->escapeValue($v);
            }

            return $result;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function unescapeValue($value)
    {
        if (is_string($value)) {
            return str_replace('%%', '%', $value);
        }

        if (is_array($value)) {
            $result = array();
            foreach ($value as $k => $v) {
                $result[$k] = $this->unescapeValue($v);
            }

            return $result;
        }

        return $value;
    }

    protected function getParameter($name, $resolving = array())
    {
        return $this->resolved ? $this->get($name) : $this->resolveValue($this->get($name), $resolving);
    }
}
