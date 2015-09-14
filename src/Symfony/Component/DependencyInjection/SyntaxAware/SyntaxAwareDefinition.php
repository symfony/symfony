<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\SyntaxAware;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A definition class that is aware of shortcut syntaxes referring to services and expressions.
 *
 * There are two main shortcuts that are available whenever it makes sense:
 *
 *  A) Shortcut for referencing services:
 *      - @logger becomes a Reference to logger
 *      - @?logger becomes an optional Reference to logger
 *
 *  B) Shortcut for creating Expressions:
 *      - @=service("foo") becomes an Expression('service("foo")')
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 */
class SyntaxAwareDefinition extends Definition
{
    /**
     * {@inheritdoc}
     */
    public function __construct($class = null, array $arguments = array())
    {
        $arguments = $this->resolveServices($arguments);

        parent::__construct($class, $arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function setArguments(array $arguments)
    {
        $arguments = $this->resolveServices($arguments);

        return parent::setArguments($arguments);
    }

    /**
     * {@inheritdoc}
     */
    public function addArgument($argument)
    {
        $argument = $this->resolveServices($argument);

        return parent::addArgument($argument);
    }

    /**
     * {@inheritdoc}
     */
    public function replaceArgument($index, $argument)
    {
        $argument = $this->resolveServices($argument);

        return parent::replaceArgument($index, $argument);
    }

    /**
     * {@inheritdoc}
     */
    public function setProperties(array $properties)
    {
        $properties = $this->resolveServices($properties);

        return parent::setProperties($properties);
    }

    /**
     * {@inheritdoc}
     */
    public function setProperty($name, $value)
    {
        $value = $this->resolveServices($value);

        return parent::setProperty($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function setFactory($factory)
    {
        if (is_string($factory)) {
            if (strpos($factory, ':') !== false && strpos($factory, '::') === false) {
                $parts = explode(':', $factory);
                $factory = array($this->resolveServices('@'.$parts[0]), $parts[1]);
            }
        } else {
            $factory = array($this->resolveServices($factory[0]), $factory[1]);
        }

        return parent::setFactory($factory);
    }

    /**
     * {@inheritdoc}
     */
    public function setConfigurator($callable)
    {
        if (is_array($callable)) {
            $callable = array($this->resolveServices($callable[0]), $callable[1]);
        }

        return parent::setConfigurator($callable);
    }

    /**
     * {@inheritdoc}
     */
    public function addMethodCall($method, array $arguments = array())
    {
        $arguments = $this->resolveServices($arguments);

        return parent::addMethodCall($method, $arguments);
    }

    /**
     * Resolves services.
     *
     * @param string|array $value
     *
     * @return array|string|Reference
     */
    private function resolveServices($value)
    {
        if (is_array($value)) {
            $value = array_map(array('self', 'resolveServices'), $value);
        } elseif (is_string($value) &&  0 === strpos($value, '@=')) {
            return new Expression(substr($value, 2));
        } elseif (is_string($value) &&  0 === strpos($value, '@')) {
            if (0 === strpos($value, '@@')) {
                $value = substr($value, 1);
                $invalidBehavior = null;
            } elseif (0 === strpos($value, '@?')) {
                $value = substr($value, 2);
                $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
            } else {
                $value = substr($value, 1);
                $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
            }

            if ('=' === substr($value, -1)) {
                $value = substr($value, 0, -1);
                $strict = false;
            } else {
                $strict = true;
            }

            if (null !== $invalidBehavior) {
                $value = new Reference($value, $invalidBehavior, $strict);
            }
        }

        return $value;
    }
}
