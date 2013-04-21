<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\Yaml\Dumper as YmlDumper;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * YamlDumper dumps a service container as a YAML string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class YamlDumper extends Dumper
{
    private $indentation = 4;
    private $dumper;

    /**
     * Constructor.
     *
     * @param ContainerBuilder $container The service container to dump
     *
     * @api
     */
    public function __construct(ContainerBuilder $container)
    {
        parent::__construct($container);

        $this->dumper = new YmlDumper();
    }

    /**
     * Dumps the service container as an YAML string.
     *
     * @param array $options An array of options
     *
     * @return string A YAML string representing of the service container
     *
     * @api
     */
    public function dump(array $options = array())
    {
        $this->indentation = isset($options['indent']) ? (int) $options['indent'] : 4;
        return $this->addParameters()."\n".$this->addServices();
    }

    /**
     * Adds a service
     *
     * @param string     $id
     * @param Definition $definition
     * @param string     $prefix
     *
     * @return string
     */
    private function addService($id, $definition, $indent = 1)
    {
        $prefix1 = str_repeat(' ', $indent * $this->indentation);
        $prefix2 = str_repeat(' ', $this->indentation);
        $prefix = $prefix1 . $prefix2;
        $code = sprintf("%s%s:\n", $prefix1, $id);
        if ($definition->getClass()) {
            $code .= sprintf("%sclass: %s\n", $prefix, $definition->getClass());
        }

        $tagsCode = '';
        foreach ($definition->getTags() as $name => $tags) {
            foreach ($tags as $attributes) {
                $att = array();
                foreach ($attributes as $key => $value) {
                    $att[] = sprintf('%s: %s', $this->dumper->dump($key), $this->dumper->dump($value));
                }
                $att = $att ? ', '.implode(' ', $att) : '';

                $tagsCode .= sprintf("%s- { name: %s%s }\n", $prefix . $prefix2, $this->dumper->dump($name), $att);
            }
        }
        if ($tagsCode) {
            $code .= sprintf("%stags:\n%s", $prefix, $tagsCode);
        }

        if ($definition->getFile()) {
            $code .= sprintf("%sfile: %s\n", $prefix, $definition->getFile());
        }

        if ($definition->isSynthetic()) {
            $code .= sprintf("%ssynthetic: true\n", $prefix);
        }

        if ($definition->isSynchronized()) {
            $code .= sprintf("%ssynchronized: true\n", $prefix);
        }

        if ($definition->getFactoryClass()) {
            $code .= sprintf("        factory_class: %s\n", $definition->getFactoryClass());
        }

        if ($definition->getFactoryMethod()) {
            $code .= sprintf("%sfactory_method: %s\n", $prefix, $definition->getFactoryMethod());
        }

        if ($definition->getFactoryService()) {
            $code .= sprintf("%sfactory_service: %s\n", $prefix, $definition->getFactoryService());
        }

        if ($definition->getArguments()) {
            $code .= sprintf("%sarguments: %s\n", $prefix, $this->dumper->dump($this->dumpValue($definition->getArguments(), $indent), 0));
        }

        if ($definition->getProperties()) {
            $code .= sprintf("%sproperties: %s\n", $prefix, $this->dumper->dump($this->dumpValue($definition->getProperties(), $indent), 0));
        }

        if ($definition->getMethodCalls()) {
            $code .= sprintf("%scalls:\n%s\n", $prefix, $this->dumper->dump($this->dumpValue($definition->getMethodCalls(), $indent), 1, 12));
        }

        if (ContainerInterface::SCOPE_CONTAINER !== $scope = $definition->getScope()) {
            $code .= sprintf("%sscope: %s\n", $prefix, $scope);
        }

        if ($callable = $definition->getConfigurator()) {
            if (is_array($callable)) {
                if ($callable[0] instanceof Reference) {
                    $callable = array($this->getServiceCall((string) $callable[0], $callable[0]), $callable[1]);
                } else {
                    $callable = array($callable[0], $callable[1]);
                }
            }

            $code .= sprintf("%sconfigurator: %s\n", $prefix, $this->dumper->dump($callable, 0));
        }

        return $code;
    }

    /**
     * Adds a service alias
     *
     * @param string $alias
     * @param Alias  $id
     *
     * @return string
     */
    private function addServiceAlias($alias, $id)
    {
        $prefix = str_repeat(' ', $this->indentation);
        if ($id->isPublic()) {
            return sprintf("%s%s: @%s\n", $prefix, $alias, $id);
        } else {
            $double_prefix = $prefix . $prefix;
            return sprintf("%s%s:\n%salias: %s\n%spublic: false", $prefix, $alias, $double_prefix, $id, $double_prefix);
        }
    }

    /**
     * Adds services
     *
     * @return string
     */
    private function addServices()
    {
        if (!$this->container->getDefinitions()) {
            return '';
        }

        $code = "services:\n";
        foreach ($this->container->getDefinitions() as $id => $definition) {
            $code .= $this->addService($id, $definition);
        }

        foreach ($this->container->getAliases() as $alias => $id) {
            $code .= $this->addServiceAlias($alias, $id);
        }

        return $code;
    }

    /**
     * Adds parameters
     *
     * @return string
     */
    private function addParameters()
    {
        if (!$this->container->getParameterBag()->all()) {
            return '';
        }

        $parameters = $this->prepareParameters($this->container->getParameterBag()->all(), $this->container->isFrozen());

        return $this->dumper->dump(array('parameters' => $parameters), 2);
    }

    /**
     * Dumps the value to YAML format
     *
     * @param mixed $value
     *
     * @return mixed
     *
     * @throws RuntimeException When trying to dump object or resource
     */
    private function dumpValue($value, $indent)
    {
        if (is_array($value)) {
            $code = array();
            foreach ($value as $k => $v) {
                $code[$k] = $this->dumpValue($v, $indent);
            }

            return $code;
        } elseif ($value instanceof Reference) {
            return $this->getServiceCall((string) $value, $value);
        } elseif ($value instanceof Parameter) {
            return $this->getParameterCall((string) $value);
        } elseif ($value instanceof Definition) {
            return $this->addService('+', $value, $indent + 1);
        } elseif (is_object($value) || is_resource($value)) {
            throw new RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
        }

        return $value;
    }

    /**
     * Gets the service call.
     *
     * @param string    $id
     * @param Reference $reference
     *
     * @return string
     */
    private function getServiceCall($id, Reference $reference = null)
    {
        if (NULL !== $reference && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $reference->getInvalidBehavior()) {
            return sprintf('@?%s', $id);
        }

        return sprintf('@%s', $id);
    }

    /**
     * Gets parameter call.
     *
     * @param string $id
     *
     * @return string
     */
    private function getParameterCall($id)
    {
        return sprintf('%%%s%%', $id);
    }

    /**
     * Prepares parameters
     *
     * @param array $parameters
     *
     * @return array
     */
    private function prepareParameters($parameters, $escape = true)
    {
        $filtered = array();
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = $this->prepareParameters($value, $escape);
            } elseif ($value instanceof Reference || is_string($value) && 0 === strpos($value, '@')) {
                $value = '@'.$value;
            }

            $filtered[$key] = $value;
        }

        return $escape ? $this->escape($filtered) : $filtered;
    }

    /**
     * Escapes arguments
     *
     * @param array $arguments
     *
     * @return array
     */
    private function escape($arguments)
    {
        $args = array();
        foreach ($arguments as $k => $v) {
            if (is_array($v)) {
                $args[$k] = $this->escape($v);
            } elseif (is_string($v)) {
                $args[$k] = str_replace('%', '%%', $v);
            } else {
                $args[$k] = $v;
            }
        }

        return $args;
    }
}
