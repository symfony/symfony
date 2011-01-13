<?php

namespace Symfony\Component\DependencyInjection\Dumper;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\InterfaceInjector;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * XmlDumper dumps a service container as an XML string.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class XmlDumper extends Dumper
{
    /**
     * Dumps the service container as an XML string.
     *
     * @param  array  $options An array of options
     *
     * @return string An xml string representing of the service container
     */
    public function dump(array $options = array())
    {
        return $this->startXml().$this->addParameters().$this->addInterfaceInjectors().$this->addServices().$this->endXml();
    }

    protected function addParameters()
    {
        if (!$this->container->getParameterBag()->all()) {
            return '';
        }

        if ($this->container->isFrozen()) {
            $parameters = $this->escape($this->container->getParameterBag()->all());
        } else {
            $parameters = $this->container->getParameterBag()->all();
        }

        return sprintf("  <parameters>\n%s  </parameters>\n", $this->convertParameters($parameters, 'parameter', 4));
    }

    protected function addInterfaceInjector(InterfaceInjector $injector)
    {
        $code = \sprintf("    <interface class=\"%s\">\n", $injector->getClass());

        foreach ($injector->getMethodCalls() as $call) {
            if (count($call[1])) {
                $code .= sprintf("      <call method=\"%s\">\n%s      </call>\n", $call[0], $this->convertParameters($call[1], 'argument', 8));
            } else {
                $code .= sprintf("      <call method=\"%s\" />\n", $call[0]);
            }
        }

        $code .= "    </interface>\n";

        return $code;
    }

    protected function addInterfaceInjectors()
    {
        if (!$this->container->getInterfaceInjectors()) {
            return '';
        }

        $code = '';
        foreach ($this->container->getInterfaceInjectors() as $injector) {
            $code .= $this->addInterfaceInjector($injector);
        }

        return sprintf("  <interfaces>\n%s  </interfaces>\n", $code);
    }

    protected function addService($definition, $id = null, $depth = 4)
    {
        $white = str_repeat(' ', $depth);
        $code = sprintf("%s<service%s%s%s%s%s>\n",
            $white,
            (null !== $id ? sprintf(' id="%s"', $id): ''),
            $definition->getClass() ? sprintf(' class="%s"', $definition->getClass()) : '',
            $definition->getFactoryMethod() ? sprintf(' factory-method="%s"', $definition->getFactoryMethod()) : '',
            $definition->getFactoryService() ? sprintf(' factory-service="%s"', $definition->getFactoryService()) : '',
            !$definition->isShared() ? ' shared="false"' : ''
        );

        foreach ($definition->getTags() as $name => $tags) {
            foreach ($tags as $attributes) {
                $att = array();
                foreach ($attributes as $key => $value) {
                    $att[] = sprintf('%s="%s"', $key, $value);
                }
                $att = $att ? ' '.implode(' ', $att) : '';

                $code .= sprintf("%s  <tag name=\"%s\"%s />\n", $white, $name, $att);
            }
        }

        if ($definition->getFile()) {
            $code .= sprintf("%s  <file>%s</file>\n", $white, $definition->getFile());
        }

        if ($definition->getArguments()) {
            $code .= $this->convertParameters($definition->getArguments(), 'argument', $depth + 2);
        }

        foreach ($definition->getMethodCalls() as $call) {
            if (count($call[1])) {
                $code .= sprintf("%s  <call method=\"%s\">\n%s%s  </call>\n", $white, $call[0], $this->convertParameters($call[1], 'argument', $depth + 4), $white);
            } else {
                $code .= sprintf("%s  <call method=\"%s\" />\n", $white, $call[0]);
            }
        }

        if ($callable = $definition->getConfigurator()) {
            if (is_array($callable)) {
                if (is_object($callable[0]) && $callable[0] instanceof Reference) {
                    $code .= sprintf("%s  <configurator service=\"%s\" method=\"%s\" />\n", $white, $callable[0], $callable[1]);
                } else {
                    $code .= sprintf("%s  <configurator class=\"%s\" method=\"%s\" />\n", $white, $callable[0], $callable[1]);
                }
            } else {
                $code .= sprintf("%s  <configurator function=\"%s\" />\n", $white, $callable);
            }
        }

        $code .= $white . "</service>\n";

        return $code;
    }

    protected function addServiceAlias($alias, $id)
    {
        if ($id->isPublic()) {
            return sprintf("    <service id=\"%s\" alias=\"%s\" />\n", $alias, $id);
        }
        return sprintf("    <service id=\"%s\" alias=\"%s\" public=\"false\" />\n", $alias, $id);
    }

    protected function addServices()
    {
        if (!$this->container->getDefinitions()) {
            return '';
        }

        $code = '';
        foreach ($this->container->getDefinitions() as $id => $definition) {
            $code .= $this->addService($definition, $id);
        }

        foreach ($this->container->getAliases() as $alias => $id) {
            $code .= $this->addServiceAlias($alias, $id);
        }

        return sprintf("  <services>\n%s  </services>\n", $code);
    }

    protected function convertParameters($parameters, $type='parameter', $depth = 2)
    {
        $white = str_repeat(' ', $depth);
        $xml = '';
        $withKeys = array_keys($parameters) !== range(0, count($parameters) - 1);
        foreach ($parameters as $key => $value) {
            $attributes = '';
            $key = $withKeys ? sprintf(' key="%s"', $key) : '';
            if (is_array($value)) {
                $value = "\n".$this->convertParameters($value, $type, $depth + 2).$white;
                $attributes = ' type="collection"';
            }

            if (is_object($value) && $value instanceof Reference) {
                $xml .= sprintf("%s<%s%s type=\"service\" id=\"%s\" %s/>\n", $white, $type, $key, (string) $value, $this->getXmlInvalidBehavior($value));

            } else if (is_object($value) && $value instanceof Definition) {
                $xml .= sprintf("%s<%s%s type=\"service\">\n%s%s</%s>\n", $white, $type, $key, $this->addService($value, null, $depth + 2), $white, $type);
            } else {
                if (in_array($value, array('null', 'true', 'false'), true)) {
                    $attributes = ' type="string"';
                }

                $xml .= sprintf("%s<%s%s%s>%s</%s>\n", $white, $type, $key, $attributes, self::phpToXml($value), $type);
            }
        }

        return $xml;
    }

    protected function startXml()
    {
        return <<<EOF
<?xml version="1.0" ?>

<container xmlns="http://www.symfony-project.org/schema/dic/services"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://www.symfony-project.org/schema/dic/services http://www.symfony-project.org/schema/dic/services/services-1.0.xsd">

EOF;
    }

    protected function endXml()
    {
        return "</container>\n";
    }

    protected function getXmlInvalidBehavior(Reference $reference)
    {
        switch ($reference->getInvalidBehavior()) {
            case ContainerInterface::NULL_ON_INVALID_REFERENCE:
                return 'on-invalid="null" ';
            case ContainerInterface::IGNORE_ON_INVALID_REFERENCE:
                return 'on-invalid="ignore" ';
            default:
                return '';
        }
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

    /**
     * @throws \RuntimeException When trying to dump object or resource
     */
    static public function phpToXml($value)
    {
        switch (true) {
            case null === $value:
                return 'null';
            case true === $value:
                return 'true';
            case false === $value:
                return 'false';
            case is_object($value) && $value instanceof Parameter:
                return '%'.$value.'%';
            case is_object($value) || is_resource($value):
                throw new \RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
            default:
                return $value;
        }
    }
}
