<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * XmlFileLoader loads XML files service definitions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class XmlFileLoader extends FileLoader
{
    const NS = 'http://symfony.com/schema/dic/services';

    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        $path = $this->locator->locate($resource);

        $xml = $this->parseFileToDOM($path);

        $this->container->addResource(new FileResource($path));

        // anonymous services
        $this->processAnonymousServices($xml, $path);

        // imports
        $this->parseImports($xml, $path);

        // parameters
        $this->parseParameters($xml, $path);

        // extensions
        $this->loadFromExtensions($xml);

        // services
        $this->parseDefinitions($xml, $path);
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'xml' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    /**
     * Parses parameters.
     *
     * @param \DOMDocument $xml
     * @param string       $file
     */
    private function parseParameters(\DOMDocument $xml, $file)
    {
        if ($parameters = $this->getChildren($xml->documentElement, 'parameters')) {
            $this->container->getParameterBag()->add($this->getArgumentsAsPhp($parameters[0], 'parameter'));
        }
    }

    /**
     * Parses imports.
     *
     * @param \DOMDocument $xml
     * @param string       $file
     */
    private function parseImports(\DOMDocument $xml, $file)
    {
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        if (false === $imports = $xpath->query('//container:imports/container:import')) {
            return;
        }

        foreach ($imports as $import) {
            $this->setCurrentDir(dirname($file));
            $this->import($import->getAttribute('resource'), null, (bool) XmlUtils::phpize($import->getAttribute('ignore-errors')), $file);
        }
    }

    /**
     * Parses multiple definitions.
     *
     * @param \DOMDocument $xml
     * @param string       $file
     */
    private function parseDefinitions(\DOMDocument $xml, $file)
    {
        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        if (false === $services = $xpath->query('//container:services/container:service')) {
            return;
        }

        foreach ($services as $service) {
            if (null !== $definition = $this->parseDefinition($service, $file)) {
                $this->container->setDefinition((string) $service->getAttribute('id'), $definition);
            }
        }
    }

    /**
     * Parses an individual Definition.
     *
     * @param \DOMElement $service
     * @param string      $file
     *
     * @return Definition|null
     */
    private function parseDefinition(\DOMElement $service, $file)
    {
        if ($alias = $service->getAttribute('alias')) {
            $public = true;
            if ($publicAttr = $service->getAttribute('public')) {
                $public = XmlUtils::phpize($publicAttr);
            }
            $this->container->setAlias((string) $service->getAttribute('id'), new Alias($alias, $public));

            return;
        }

        if ($parent = $service->getAttribute('parent')) {
            $definition = new DefinitionDecorator($parent);
        } else {
            $definition = new Definition();
        }

        foreach (array('class', 'shared', 'public', 'factory-class', 'factory-method', 'factory-service', 'synthetic', 'lazy', 'abstract') as $key) {
            if ($value = $service->getAttribute($key)) {
                if (in_array($key, array('factory-class', 'factory-method', 'factory-service'))) {
                    @trigger_error(sprintf('The "%s" attribute in file "%s" is deprecated since version 2.6 and will be removed in 3.0. Use the "factory" element instead.', $key, $file), E_USER_DEPRECATED);
                }
                $method = 'set'.str_replace('-', '', $key);
                $definition->$method(XmlUtils::phpize($value));
            }
        }

        if ($value = $service->getAttribute('scope')) {
            $triggerDeprecation = 'request' !== (string) $service->getAttribute('id');

            if ($triggerDeprecation) {
                @trigger_error(sprintf('The "scope" attribute of service "%s" in file "%s" is deprecated since version 2.8 and will be removed in 3.0.', (string) $service->getAttribute('id'), $file), E_USER_DEPRECATED);
            }

            $definition->setScope(XmlUtils::phpize($value), false);
        }

        if ($value = $service->getAttribute('synchronized')) {
            $triggerDeprecation = 'request' !== (string) $service->getAttribute('id');

            if ($triggerDeprecation) {
                @trigger_error(sprintf('The "synchronized" attribute in file "%s" is deprecated since version 2.7 and will be removed in 3.0.', $file), E_USER_DEPRECATED);
            }

            $definition->setSynchronized(XmlUtils::phpize($value), $triggerDeprecation);
        }

        if ($files = $this->getChildren($service, 'file')) {
            $definition->setFile($files[0]->nodeValue);
        }

        $definition->setArguments($this->getArgumentsAsPhp($service, 'argument'));
        $definition->setProperties($this->getArgumentsAsPhp($service, 'property'));

        if ($factories = $this->getChildren($service, 'factory')) {
            $factory = $factories[0];
            if ($function = $factory->getAttribute('function')) {
                $definition->setFactory($function);
            } else {
                $factoryService = $this->getChildren($factory, 'service');

                if (isset($factoryService[0])) {
                    $class = $this->parseDefinition($factoryService[0], $file);
                } elseif ($childService = $factory->getAttribute('service')) {
                    $class = new Reference($childService, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false);
                } else {
                    $class = $factory->getAttribute('class');
                }

                $definition->setFactory(array($class, $factory->getAttribute('method')));
            }
        }

        if ($configurators = $this->getChildren($service, 'configurator')) {
            $configurator = $configurators[0];
            if ($function = $configurator->getAttribute('function')) {
                $definition->setConfigurator($function);
            } else {
                $configuratorService = $this->getChildren($configurator, 'service');

                if (isset($configuratorService[0])) {
                    $class = $this->parseDefinition($configuratorService[0], $file);
                } elseif ($childService = $configurator->getAttribute('service')) {
                    $class = new Reference($childService, ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false);
                } else {
                    $class = $configurator->getAttribute('class');
                }

                $definition->setConfigurator(array($class, $configurator->getAttribute('method')));
            }
        }

        foreach ($this->getChildren($service, 'call') as $call) {
            $definition->addMethodCall($call->getAttribute('method'), $this->getArgumentsAsPhp($call, 'argument'));
        }

        foreach ($this->getChildren($service, 'tag') as $tag) {
            $parameters = array();
            foreach ($tag->attributes as $name => $node) {
                if ('name' === $name) {
                    continue;
                }

                if (false !== strpos($name, '-') && false === strpos($name, '_') && !array_key_exists($normalizedName = str_replace('-', '_', $name), $parameters)) {
                    $parameters[$normalizedName] = XmlUtils::phpize($node->nodeValue);
                }
                // keep not normalized key for BC too
                $parameters[$name] = XmlUtils::phpize($node->nodeValue);
            }

            $definition->addTag($tag->getAttribute('name'), $parameters);
        }

        if ($value = $service->getAttribute('decorates')) {
            $renameId = $service->hasAttribute('decoration-inner-name') ? $service->getAttribute('decoration-inner-name') : null;
            $definition->setDecoratedService($value, $renameId);
        }

        return $definition;
    }

    /**
     * Parses a XML file to a \DOMDocument.
     *
     * @param string $file Path to a file
     *
     * @return \DOMDocument
     *
     * @throws InvalidArgumentException When loading of XML file returns error
     */
    private function parseFileToDOM($file)
    {
        try {
            $dom = XmlUtils::loadFile($file, array($this, 'validateSchema'));
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException(sprintf('Unable to parse file "%s".', $file), $e->getCode(), $e);
        }

        $this->validateExtensions($dom, $file);

        return $dom;
    }

    /**
     * Processes anonymous services.
     *
     * @param \DOMDocument $xml
     * @param string       $file
     */
    private function processAnonymousServices(\DOMDocument $xml, $file)
    {
        $definitions = array();
        $count = 0;

        $xpath = new \DOMXPath($xml);
        $xpath->registerNamespace('container', self::NS);

        // anonymous services as arguments/properties
        if (false !== $nodes = $xpath->query('//container:argument[@type="service"][not(@id)]|//container:property[@type="service"][not(@id)]')) {
            foreach ($nodes as $node) {
                // give it a unique name
                $id = sprintf('%s_%d', hash('sha256', $file), ++$count);
                $node->setAttribute('id', $id);

                if ($services = $this->getChildren($node, 'service')) {
                    $definitions[$id] = array($services[0], $file, false);
                    $services[0]->setAttribute('id', $id);
                }
            }
        }

        // anonymous services "in the wild"
        if (false !== $nodes = $xpath->query('//container:services/container:service[not(@id)]')) {
            foreach ($nodes as $node) {
                // give it a unique name
                $id = sprintf('%s_%d', hash('sha256', $file), ++$count);
                $node->setAttribute('id', $id);

                if ($services = $this->getChildren($node, 'service')) {
                    $definitions[$id] = array($node, $file, true);
                    $services[0]->setAttribute('id', $id);
                }
            }
        }

        // resolve definitions
        krsort($definitions);
        foreach ($definitions as $id => $def) {
            list($domElement, $file, $wild) = $def;

            // anonymous services are always private
            // we could not use the constant false here, because of XML parsing
            $domElement->setAttribute('public', 'false');

            if (null !== $definition = $this->parseDefinition($domElement, $file)) {
                $this->container->setDefinition($id, $definition);
            }

            if (true === $wild) {
                $tmpDomElement = new \DOMElement('_services', null, self::NS);
                $domElement->parentNode->replaceChild($tmpDomElement, $domElement);
                $tmpDomElement->setAttribute('id', $id);
            } else {
                $domElement->parentNode->removeChild($domElement);
            }
        }
    }

    /**
     * Returns arguments as valid php types.
     *
     * @param \DOMElement $node
     * @param string      $name
     * @param bool        $lowercase
     *
     * @return mixed
     */
    private function getArgumentsAsPhp(\DOMElement $node, $name, $lowercase = true)
    {
        $arguments = array();
        foreach ($this->getChildren($node, $name) as $arg) {
            if ($arg->hasAttribute('name')) {
                $arg->setAttribute('key', $arg->getAttribute('name'));
            }

            if (!$arg->hasAttribute('key')) {
                $key = !$arguments ? 0 : max(array_keys($arguments)) + 1;
            } else {
                $key = $arg->getAttribute('key');
            }

            // parameter keys are case insensitive
            if ('parameter' == $name && $lowercase) {
                $key = strtolower($key);
            }

            // this is used by DefinitionDecorator to overwrite a specific
            // argument of the parent definition
            if ($arg->hasAttribute('index')) {
                $key = 'index_'.$arg->getAttribute('index');
            }

            switch ($arg->getAttribute('type')) {
                case 'service':
                    $onInvalid = $arg->getAttribute('on-invalid');
                    $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
                    if ('ignore' == $onInvalid) {
                        $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
                    } elseif ('null' == $onInvalid) {
                        $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
                    }

                    if ($strict = $arg->getAttribute('strict')) {
                        $strict = XmlUtils::phpize($strict);
                    } else {
                        $strict = true;
                    }

                    $arguments[$key] = new Reference($arg->getAttribute('id'), $invalidBehavior, $strict);
                    break;
                case 'expression':
                    $arguments[$key] = new Expression($arg->nodeValue);
                    break;
                case 'collection':
                    $arguments[$key] = $this->getArgumentsAsPhp($arg, $name, false);
                    break;
                case 'string':
                    $arguments[$key] = $arg->nodeValue;
                    break;
                case 'constant':
                    $arguments[$key] = constant($arg->nodeValue);
                    break;
                default:
                    $arguments[$key] = XmlUtils::phpize($arg->nodeValue);
            }
        }

        return $arguments;
    }

    /**
     * Get child elements by name.
     *
     * @param \DOMNode $node
     * @param mixed    $name
     *
     * @return array
     */
    private function getChildren(\DOMNode $node, $name)
    {
        $children = array();
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $name && $child->namespaceURI === self::NS) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Validates a documents XML schema.
     *
     * @param \DOMDocument $dom
     *
     * @return bool
     *
     * @throws RuntimeException When extension references a non-existent XSD file
     */
    public function validateSchema(\DOMDocument $dom)
    {
        $schemaLocations = array('http://symfony.com/schema/dic/services' => str_replace('\\', '/', __DIR__.'/schema/dic/services/services-1.0.xsd'));

        if ($element = $dom->documentElement->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation')) {
            $items = preg_split('/\s+/', $element);
            for ($i = 0, $nb = count($items); $i < $nb; $i += 2) {
                if (!$this->container->hasExtension($items[$i])) {
                    continue;
                }

                if (($extension = $this->container->getExtension($items[$i])) && false !== $extension->getXsdValidationBasePath()) {
                    $path = str_replace($extension->getNamespace(), str_replace('\\', '/', $extension->getXsdValidationBasePath()).'/', $items[$i + 1]);

                    if (!is_file($path)) {
                        throw new RuntimeException(sprintf('Extension "%s" references a non-existent XSD file "%s"', get_class($extension), $path));
                    }

                    $schemaLocations[$items[$i]] = $path;
                }
            }
        }

        $tmpfiles = array();
        $imports = '';
        foreach ($schemaLocations as $namespace => $location) {
            $parts = explode('/', $location);
            if (0 === stripos($location, 'phar://')) {
                $tmpfile = tempnam(sys_get_temp_dir(), 'sf2');
                if ($tmpfile) {
                    copy($location, $tmpfile);
                    $tmpfiles[] = $tmpfile;
                    $parts = explode('/', str_replace('\\', '/', $tmpfile));
                }
            }
            $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
            $location = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));

            $imports .= sprintf('  <xsd:import namespace="%s" schemaLocation="%s" />'."\n", $namespace, $location);
        }

        $source = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<xsd:schema xmlns="http://symfony.com/schema"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    targetNamespace="http://symfony.com/schema"
    elementFormDefault="qualified">

    <xsd:import namespace="http://www.w3.org/XML/1998/namespace"/>
$imports
</xsd:schema>
EOF
        ;

        $valid = @$dom->schemaValidateSource($source);

        foreach ($tmpfiles as $tmpfile) {
            @unlink($tmpfile);
        }

        return $valid;
    }

    /**
     * Validates an extension.
     *
     * @param \DOMDocument $dom
     * @param string       $file
     *
     * @throws InvalidArgumentException When no extension is found corresponding to a tag
     */
    private function validateExtensions(\DOMDocument $dom, $file)
    {
        foreach ($dom->documentElement->childNodes as $node) {
            if (!$node instanceof \DOMElement || 'http://symfony.com/schema/dic/services' === $node->namespaceURI) {
                continue;
            }

            // can it be handled by an extension?
            if (!$this->container->hasExtension($node->namespaceURI)) {
                $extensionNamespaces = array_filter(array_map(function ($ext) { return $ext->getNamespace(); }, $this->container->getExtensions()));
                throw new InvalidArgumentException(sprintf(
                    'There is no extension able to load the configuration for "%s" (in %s). Looked for namespace "%s", found %s',
                    $node->tagName,
                    $file,
                    $node->namespaceURI,
                    $extensionNamespaces ? sprintf('"%s"', implode('", "', $extensionNamespaces)) : 'none'
                ));
            }
        }
    }

    /**
     * Loads from an extension.
     *
     * @param \DOMDocument $xml
     */
    private function loadFromExtensions(\DOMDocument $xml)
    {
        foreach ($xml->documentElement->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->namespaceURI === self::NS) {
                continue;
            }

            $values = static::convertDomElementToArray($node);
            if (!is_array($values)) {
                $values = array();
            }

            $this->container->loadFromExtension($node->namespaceURI, $values);
        }
    }

    /**
     * Converts a \DomElement object to a PHP array.
     *
     * The following rules applies during the conversion:
     *
     *  * Each tag is converted to a key value or an array
     *    if there is more than one "value"
     *
     *  * The content of a tag is set under a "value" key (<foo>bar</foo>)
     *    if the tag also has some nested tags
     *
     *  * The attributes are converted to keys (<foo foo="bar"/>)
     *
     *  * The nested-tags are converted to keys (<foo><foo>bar</foo></foo>)
     *
     * @param \DomElement $element A \DomElement instance
     *
     * @return array A PHP array
     */
    public static function convertDomElementToArray(\DomElement $element)
    {
        return XmlUtils::convertDomElementToArray($element);
    }
}
