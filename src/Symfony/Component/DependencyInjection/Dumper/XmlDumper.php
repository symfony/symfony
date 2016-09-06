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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * XmlDumper dumps a service container as an XML string.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class XmlDumper extends Dumper
{
    /**
     * @var \DOMDocument
     */
    private $document;

    /**
     * Dumps the service container as an XML string.
     *
     * @param array $options An array of options
     *
     * @return string An xml string representing of the service container
     */
    public function dump(array $options = array())
    {
        $this->document = new \DOMDocument('1.0', 'utf-8');
        $this->document->formatOutput = true;

        $container = $this->document->createElementNS('http://symfony.com/schema/dic/services', 'container');
        $container->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $container->setAttribute('xsi:schemaLocation', 'http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd');

        $this->addParameters($container);
        $this->addServices($container);

        $this->document->appendChild($container);
        $xml = $this->document->saveXML();
        $this->document = null;

        return $xml;
    }

    /**
     * Adds parameters.
     *
     * @param \DOMElement $parent
     */
    private function addParameters(\DOMElement $parent)
    {
        $data = $this->container->getParameterBag()->all();
        if (!$data) {
            return;
        }

        if ($this->container->isFrozen()) {
            $data = $this->escape($data);
        }

        $parameters = $this->document->createElement('parameters');
        $parent->appendChild($parameters);
        $this->convertParameters($data, 'parameter', $parameters);
    }

    /**
     * Adds method calls.
     *
     * @param array       $methodcalls
     * @param \DOMElement $parent
     */
    private function addMethodCalls(array $methodcalls, \DOMElement $parent)
    {
        foreach ($methodcalls as $methodcall) {
            $call = $this->document->createElement('call');
            $call->setAttribute('method', $methodcall[0]);
            if (count($methodcall[1])) {
                $this->convertParameters($methodcall[1], 'argument', $call);
            }
            $parent->appendChild($call);
        }
    }

    /**
     * Adds a service.
     *
     * @param Definition  $definition
     * @param string      $id
     * @param \DOMElement $parent
     */
    private function addService($definition, $id, \DOMElement $parent)
    {
        $service = $this->document->createElement('service');
        if (null !== $id) {
            $service->setAttribute('id', $id);
        }
        if ($class = $definition->getClass()) {
            if ('\\' === substr($class, 0, 1)) {
                $class = substr($class, 1);
            }

            $service->setAttribute('class', $class);
        }
        if ($definition->getFactoryMethod(false)) {
            $service->setAttribute('factory-method', $definition->getFactoryMethod(false));
        }
        if ($definition->getFactoryClass(false)) {
            $service->setAttribute('factory-class', $definition->getFactoryClass(false));
        }
        if ($definition->getFactoryService(false)) {
            $service->setAttribute('factory-service', $definition->getFactoryService(false));
        }
        if (ContainerInterface::SCOPE_CONTAINER !== $scope = $definition->getScope()) {
            $service->setAttribute('scope', $scope);
        }
        if (!$definition->isPublic()) {
            $service->setAttribute('public', 'false');
        }
        if ($definition->isSynthetic()) {
            $service->setAttribute('synthetic', 'true');
        }
        if ($definition->isSynchronized(false)) {
            $service->setAttribute('synchronized', 'true');
        }
        if ($definition->isLazy()) {
            $service->setAttribute('lazy', 'true');
        }
        if (null !== $decorated = $definition->getDecoratedService()) {
            list($decorated, $renamedId) = $decorated;
            $service->setAttribute('decorates', $decorated);
            if (null !== $renamedId) {
                $service->setAttribute('decoration-inner-name', $renamedId);
            }
        }

        foreach ($definition->getTags() as $name => $tags) {
            foreach ($tags as $attributes) {
                $tag = $this->document->createElement('tag');
                $tag->setAttribute('name', $name);
                foreach ($attributes as $key => $value) {
                    $tag->setAttribute($key, $value);
                }
                $service->appendChild($tag);
            }
        }

        if ($definition->getFile()) {
            $file = $this->document->createElement('file');
            $file->appendChild($this->document->createTextNode($definition->getFile()));
            $service->appendChild($file);
        }

        if ($parameters = $definition->getArguments()) {
            $this->convertParameters($parameters, 'argument', $service);
        }

        if ($parameters = $definition->getProperties()) {
            $this->convertParameters($parameters, 'property', $service, 'name');
        }

        $this->addMethodCalls($definition->getMethodCalls(), $service);

        if ($callable = $definition->getFactory()) {
            $factory = $this->document->createElement('factory');

            if (is_array($callable) && $callable[0] instanceof Definition) {
                $this->addService($callable[0], null, $factory);
                $factory->setAttribute('method', $callable[1]);
            } elseif (is_array($callable)) {
                $factory->setAttribute($callable[0] instanceof Reference ? 'service' : 'class', $callable[0]);
                $factory->setAttribute('method', $callable[1]);
            } else {
                $factory->setAttribute('function', $callable);
            }
            $service->appendChild($factory);
        }

        if ($callable = $definition->getConfigurator()) {
            $configurator = $this->document->createElement('configurator');

            if (is_array($callable) && $callable[0] instanceof Definition) {
                $this->addService($callable[0], null, $configurator);
                $configurator->setAttribute('method', $callable[1]);
            } elseif (is_array($callable)) {
                $configurator->setAttribute($callable[0] instanceof Reference ? 'service' : 'class', $callable[0]);
                $configurator->setAttribute('method', $callable[1]);
            } else {
                $configurator->setAttribute('function', $callable);
            }
            $service->appendChild($configurator);
        }

        $parent->appendChild($service);
    }

    /**
     * Adds a service alias.
     *
     * @param string      $alias
     * @param Alias       $id
     * @param \DOMElement $parent
     */
    private function addServiceAlias($alias, Alias $id, \DOMElement $parent)
    {
        $service = $this->document->createElement('service');
        $service->setAttribute('id', $alias);
        $service->setAttribute('alias', $id);
        if (!$id->isPublic()) {
            $service->setAttribute('public', 'false');
        }
        $parent->appendChild($service);
    }

    /**
     * Adds services.
     *
     * @param \DOMElement $parent
     */
    private function addServices(\DOMElement $parent)
    {
        $definitions = $this->container->getDefinitions();
        if (!$definitions) {
            return;
        }

        $services = $this->document->createElement('services');
        foreach ($definitions as $id => $definition) {
            $this->addService($definition, $id, $services);
        }

        $aliases = $this->container->getAliases();
        foreach ($aliases as $alias => $id) {
            while (isset($aliases[(string) $id])) {
                $id = $aliases[(string) $id];
            }
            $this->addServiceAlias($alias, $id, $services);
        }
        $parent->appendChild($services);
    }

    /**
     * Converts parameters.
     *
     * @param array       $parameters
     * @param string      $type
     * @param \DOMElement $parent
     * @param string      $keyAttribute
     */
    private function convertParameters(array $parameters, $type, \DOMElement $parent, $keyAttribute = 'key')
    {
        $withKeys = array_keys($parameters) !== range(0, count($parameters) - 1);
        foreach ($parameters as $key => $value) {
            $element = $this->document->createElement($type);
            if ($withKeys) {
                $element->setAttribute($keyAttribute, $key);
            }

            if (is_array($value)) {
                $element->setAttribute('type', 'collection');
                $this->convertParameters($value, $type, $element, 'key');
            } elseif ($value instanceof Reference) {
                $element->setAttribute('type', 'service');
                $element->setAttribute('id', (string) $value);
                $behaviour = $value->getInvalidBehavior();
                if ($behaviour == ContainerInterface::NULL_ON_INVALID_REFERENCE) {
                    $element->setAttribute('on-invalid', 'null');
                } elseif ($behaviour == ContainerInterface::IGNORE_ON_INVALID_REFERENCE) {
                    $element->setAttribute('on-invalid', 'ignore');
                }
                if (!$value->isStrict()) {
                    $element->setAttribute('strict', 'false');
                }
            } elseif ($value instanceof Definition) {
                $element->setAttribute('type', 'service');
                $this->addService($value, null, $element);
            } elseif ($value instanceof Expression) {
                $element->setAttribute('type', 'expression');
                $text = $this->document->createTextNode(self::phpToXml((string) $value));
                $element->appendChild($text);
            } else {
                if (in_array($value, array('null', 'true', 'false'), true)) {
                    $element->setAttribute('type', 'string');
                }
                $text = $this->document->createTextNode(self::phpToXml($value));
                $element->appendChild($text);
            }
            $parent->appendChild($element);
        }
    }

    /**
     * Escapes arguments.
     *
     * @param array $arguments
     *
     * @return array
     */
    private function escape(array $arguments)
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
     * Converts php types to xml types.
     *
     * @param mixed $value Value to convert
     *
     * @return string
     *
     * @throws RuntimeException When trying to dump object or resource
     */
    public static function phpToXml($value)
    {
        switch (true) {
            case null === $value:
                return 'null';
            case true === $value:
                return 'true';
            case false === $value:
                return 'false';
            case $value instanceof Parameter:
                return '%'.$value.'%';
            case is_object($value) || is_resource($value):
                throw new RuntimeException('Unable to dump a service container if a parameter is an object or a resource.');
            default:
                return (string) $value;
        }
    }
}
