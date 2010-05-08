<?php

namespace Symfony\Components\DependencyInjection\Dumper;

use Symfony\Components\Yaml\Yaml;
use Symfony\Components\DependencyInjection\Container;
use Symfony\Components\DependencyInjection\Parameter;
use Symfony\Components\DependencyInjection\Reference;

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
 * @package    Symfony
 * @subpackage Components_DependencyInjection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
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
        return $this->addParameters()."\n".$this->addServices();
    }

    protected function addService($id, $definition)
    {
        $code = "  $id:\n";
        $code .= sprintf("    class: %s\n", $definition->getClass());

        $annotationsCode = '';
        foreach ($definition->getAnnotations() as $name => $annotations) {
            foreach ($annotations as $attributes) {
                $att = array();
                foreach ($attributes as $key => $value) {
                    $att[] = sprintf('%s: %s', Yaml::dump($key), Yaml::dump($value));
                }
                $att = $att ? ', '.implode(' ', $att) : '';

                $annotationsCode .= sprintf("      - { name: %s%s }\n", Yaml::dump($name), $att);
            }
        }
        if ($annotationsCode) {
            $code .= "    annotations:\n".$annotationsCode;
        }

        if ($definition->getFile()) {
            $code .= sprintf("    file: %s\n", $definition->getFile());
        }

        if ($definition->getConstructor()) {
            $code .= sprintf("    constructor: %s\n", $definition->getConstructor());
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
        return sprintf("  %s: @%s\n", $alias, $id);
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
        if (!$this->container->getParameters()) {
            return '';
        }

        return Yaml::dump(array('parameters' => $this->prepareParameters($this->container->getParameters())), 2);
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
        if (null !== $reference && Container::EXCEPTION_ON_INVALID_REFERENCE !== $reference->getInvalidBehavior()) {
            return sprintf('@@%s', $id);
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
