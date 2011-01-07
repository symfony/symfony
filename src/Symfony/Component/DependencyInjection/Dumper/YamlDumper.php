<?php

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * YamlDumper dumps a service container as a YAML string.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class YamlDumper extends Dumper
{
    /**
     * Dumps the service container as an YAML string.
     *
     * @param  array  $options An array of options
     *
     * @return string A YAML string representing of the service container
     */
    public function dump(array $options = array())
    {
        return $this->addParameters().$this->addInterfaceInjectors()."\n".$this->addServices();
    }

    protected function addInterfaceInjectors()
    {
        if (!$this->container->getInterfaceInjectors()) {
            return '';
        }

        $code = "\ninterfaces:\n";
        foreach ($this->container->getInterfaceInjectors() as $injector) {
            $code .= sprintf("    %s:\n", $injector->getClass());
            if ($injector->getMethodCalls()) {
                $code .= sprintf("        calls:\n          %s\n", str_replace("\n", "\n          ", Yaml::dump($this->dumpValue($injector->getMethodCalls()), 1)));
            }
        }

        return $code;
    }

    protected function addService($id, $definition)
    {
        $code = "  $id:\n";
        if ($definition->getClass()) {
            $code .= sprintf("    class: %s\n", $definition->getClass());
        }

        $tagsCode = '';
        foreach ($definition->getTags() as $name => $tags) {
            foreach ($tags as $attributes) {
                $att = array();
                foreach ($attributes as $key => $value) {
                    $att[] = sprintf('%s: %s', Yaml::dump($key), Yaml::dump($value));
                }
                $att = $att ? ', '.implode(' ', $att) : '';

                $tagsCode .= sprintf("      - { name: %s%s }\n", Yaml::dump($name), $att);
            }
        }
        if ($tagsCode) {
            $code .= "    tags:\n".$tagsCode;
        }

        if ($definition->getFile()) {
            $code .= sprintf("    file: %s\n", $definition->getFile());
        }

        if ($definition->getFactoryMethod()) {
            $code .= sprintf("    factory_method: %s\n", $definition->getFactoryMethod());
        }

        if ($definition->getFactoryService()) {
            $code .= sprintf("    factory_service: %s\n", $definition->getFactoryService());
        }

        if ($definition->getArguments()) {
            $code .= sprintf("    arguments: %s\n", Yaml::dump($this->dumpValue($definition->getArguments()), 0));
        }

        if ($definition->getMethodCalls()) {
            $code .= sprintf("    calls:\n      %s\n", str_replace("\n", "\n      ", Yaml::dump($this->dumpValue($definition->getMethodCalls()), 1)));
        }

        if (!$definition->isShared()) {
            $code .= "    shared: false\n";
        }

        if ($callable = $definition->getConfigurator()) {
            if (is_array($callable)) {
                if (is_object($callable[0]) && $callable[0] instanceof Reference) {
                    $callable = array($this->getServiceCall((string) $callable[0], $callable[0]), $callable[1]);
                } else {
                    $callable = array($callable[0], $callable[1]);
                }
            }

            $code .= sprintf("    configurator: %s\n", Yaml::dump($callable, 0));
        }

        return $code;
    }

    protected function addServiceAlias($alias, $id)
    {
        if ($id->isPublic()) {
            return sprintf("  %s: @%s\n", $alias, $id);
        } else {
            return sprintf("  %s:\n    alias: %s\n    public: false", $alias, $id);
        }
    }

    protected function addServices()
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

    protected function addParameters()
    {
        if (!$this->container->getParameterBag()->all()) {
            return '';
        }

        if ($this->container->isFrozen()) {
            $parameters = $this->prepareParameters($this->container->getParameterBag()->all());
        } else {
            $parameters = $this->container->getParameterBag()->all();
        }

        return Yaml::dump(array('parameters' => $parameters), 2);
    }

    /**
     * @throws \RuntimeException When trying to dump object or resource
     */
    protected function dumpValue($value)
    {
        if (is_array($value)) {
            $code = array();
            foreach ($value as $k => $v) {
                $code[$k] = $this->dumpValue($v);
            }

            return $code;
        } elseif (is_object($value) && $value instanceof Reference) {
            return $this->getServiceCall((string) $value, $value);
        } elseif (is_object($value) && $value instanceof Parameter) {
            return $this->getParameterCall((string) $value);
        } elseif (is_object($value) || is_resource($value)) {
            throw new \RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
        } else {
            return $value;
        }
    }

    protected function getServiceCall($id, Reference $reference = null)
    {
        if (null !== $reference && ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE !== $reference->getInvalidBehavior()) {
            return sprintf('@?%s', $id);
        } else {
            return sprintf('@%s', $id);
        }
    }

    protected function getParameterCall($id)
    {
        return sprintf('%%%s%%', $id);
    }

    protected function prepareParameters($parameters)
    {
        $filtered = array();
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $value = $this->prepareParameters($value);
            } elseif ($value instanceof Reference) {
                $value = '@'.$value;
            }

            $filtered[$key] = $value;
        }

        return $this->escape($filtered);
    }

    protected function escape($arguments)
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
