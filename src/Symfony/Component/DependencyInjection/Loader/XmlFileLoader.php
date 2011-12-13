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

use Symfony\Component\DependencyInjection\DefinitionDecorator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\SimpleXMLElement;
use Symfony\Component\Config\Resource\FileResource;

/**
 * XmlFileLoader loads XML files service definitions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class XmlFileLoader extends FileLoader
{
    /**
     * Loads an XML file.
     *
     * @param mixed  $file The resource
     * @param string $type The resource type
     */
    public function load($file, $type = null)
    {
        $path = $this->locator->locate($file);

        $xml = $this->parseFile($path);
        $xml->registerXPathNamespace('container', 'http://symfony.com/schema/dic/services');

        $this->container->addResource(new FileResource($path));

        // anonymous services
        $xml = $this->processAnonymousServices($xml, $path);

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
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'xml' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    /**
     * Parses parameters
     *
     * @param SimpleXMLElement $xml
     * @param string $file
     *
     * @return void
     */
    private function parseParameters(SimpleXMLElement $xml, $file)
    {
        if (!$xml->parameters) {
            return;
        }

        $this->container->getParameterBag()->add($xml->parameters->getArgumentsAsPhp('parameter'));
    }

    /**
     * Parses imports
     *
     * @param SimpleXMLElement $xml
     * @param string $file
     *
     * @return void
     */
    private function parseImports(SimpleXMLElement $xml, $file)
    {
        if (false === $imports = $xml->xpath('//container:imports/container:import')) {
            return;
        }

        foreach ($imports as $import) {
            $this->setCurrentDir(dirname($file));
            $this->import((string) $import['resource'], null, (Boolean) $import->getAttributeAsPhp('ignore-errors'), $file);
        }
    }

    /**
     * Parses multiple definitions
     *
     * @param SimpleXMLElement $xml
     * @param string $file
     *
     * @return void
     */
    private function parseDefinitions(SimpleXMLElement $xml, $file)
    {
        if (false === $services = $xml->xpath('//container:services/container:service')) {
            return;
        }

        foreach ($services as $service) {
            $this->parseDefinition((string) $service['id'], $service, $file);
        }
    }

    /**
     * Parses an individual Definition
     *
     * @param string $id
     * @param SimpleXMLElement $service
     * @param string $file
     *
     * @return void
     */
    private function parseDefinition($id, $service, $file)
    {
        if ((string) $service['alias']) {
            $public = true;
            if (isset($service['public'])) {
                $public = $service->getAttributeAsPhp('public');
            }
            $this->container->setAlias($id, new Alias((string) $service['alias'], $public));

            return;
        }

        if (isset($service['parent'])) {
            $definition = new DefinitionDecorator((string) $service['parent']);
        } else {
            $definition = new Definition();
        }

        foreach (array('class', 'scope', 'public', 'factory-class', 'factory-method', 'factory-service', 'synthetic', 'abstract') as $key) {
            if (isset($service[$key])) {
                $method = 'set'.str_replace('-', '', $key);
                $definition->$method((string) $service->getAttributeAsPhp($key));
            }
        }

        if ($service->file) {
            $definition->setFile((string) $service->file);
        }

        $definition->setArguments($service->getArgumentsAsPhp('argument'));
        $definition->setProperties($service->getArgumentsAsPhp('property'));

        if (isset($service->configurator)) {
            if (isset($service->configurator['function'])) {
                $definition->setConfigurator((string) $service->configurator['function']);
            } else {
                if (isset($service->configurator['service'])) {
                    $class = new Reference((string) $service->configurator['service'], ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false);
                } else {
                    $class = (string) $service->configurator['class'];
                }

                $definition->setConfigurator(array($class, (string) $service->configurator['method']));
            }
        }

        foreach ($service->call as $call) {
            $definition->addMethodCall((string) $call['method'], $call->getArgumentsAsPhp('argument'));
        }

        foreach ($service->tag as $tag) {
            $parameters = array();
            foreach ($tag->attributes() as $name => $value) {
                if ('name' === $name) {
                    continue;
                }

                $parameters[$name] = SimpleXMLElement::phpize($value);
            }

            $definition->addTag((string) $tag['name'], $parameters);
        }

        $this->container->setDefinition($id, $definition);
    }

    /**
     * Parses a XML file.
     *
     * @param string $file Path to a file
     *
     * @throws \InvalidArgumentException When loading of XML file returns error
     */
    private function parseFile($file)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->load($file, defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        $dom->validateOnParse = true;
        $dom->normalizeDocument();
        libxml_use_internal_errors(false);
        $this->validate($dom, $file);

        return simplexml_import_dom($dom, 'Symfony\\Component\\DependencyInjection\\SimpleXMLElement');
    }

    /**
     * Processes anonymous services
     *
     * @param SimpleXMLElement $xml
     * @param string $file
     *
     * @return array An array of anonymous services
     */
    private function processAnonymousServices(SimpleXMLElement $xml, $file)
    {
        $definitions = array();
        $count = 0;

        // anonymous services as arguments
        if (false === $nodes = $xml->xpath('//container:argument[@type="service"][not(@id)]')) {
            return $xml;
        }
        foreach ($nodes as $node) {
            // give it a unique name
            $node['id'] = sprintf('%s_%d', md5($file), ++$count);

            $definitions[(string) $node['id']] = array($node->service, $file, false);
            $node->service['id'] = (string) $node['id'];
        }

        // anonymous services "in the wild"
        if (false === $nodes = $xml->xpath('//container:services/container:service[not(@id)]')) {
            return $xml;
        }
        foreach ($nodes as $node) {
            // give it a unique name
            $node['id'] = sprintf('%s_%d', md5($file), ++$count);

            $definitions[(string) $node['id']] = array($node, $file, true);
            $node->service['id'] = (string) $node['id'];
        }

        // resolve definitions
        krsort($definitions);
        foreach ($definitions as $id => $def) {
            // anonymous services are always private
            $def[0]['public'] = false;

            $this->parseDefinition($id, $def[0], $def[1]);

            $oNode = dom_import_simplexml($def[0]);
            if (true === $def[2]) {
                $nNode = new \DOMElement('_services');
                $oNode->parentNode->replaceChild($nNode, $oNode);
                $nNode->setAttribute('id', $id);
            } else {
                $oNode->parentNode->removeChild($oNode);
            }
        }

        return $xml;
    }

    /**
     * Validates an XML document.
     *
     * @param DOMDocument $dom
     * @param string $file
     */
    private function validate(\DOMDocument $dom, $file)
    {
        $this->validateSchema($dom, $file);
        $this->validateExtensions($dom, $file);
    }

    /**
     * Validates a documents XML schema.
     *
     * @param \DOMDocument $dom
     * @param string $file
     *
     * @return void
     *
     * @throws \RuntimeException         When extension references a non-existent XSD file
     * @throws \InvalidArgumentException When xml doesn't validate its xsd schema
     */
    private function validateSchema(\DOMDocument $dom, $file)
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

                    if (!file_exists($path)) {
                        throw new \RuntimeException(sprintf('Extension "%s" references a non-existent XSD file "%s"', get_class($extension), $path));
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

        $current = libxml_use_internal_errors(true);
        $valid = $dom->schemaValidateSource($source);
        foreach ($tmpfiles as $tmpfile) {
            @unlink($tmpfile);
        }
        if (!$valid) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        libxml_use_internal_errors($current);
    }

    /**
     * Validates an extension.
     *
     * @param \DOMDocument $dom
     * @param string $file
     *
     * @return void
     *
     * @throws  \InvalidArgumentException When non valid tag are found or no extension are found
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
                throw new \InvalidArgumentException(sprintf(
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
     * Returns an array of XML errors.
     *
     * @return array
     */
    private function getXmlErrors()
    {
        $errors = array();
        foreach (libxml_get_errors() as $error) {
            $errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
                LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR',
                $error->code,
                trim($error->message),
                $error->file ? $error->file : 'n/a',
                $error->line,
                $error->column
            );
        }

        libxml_clear_errors();

        return $errors;
    }

    /**
     * Loads from an extension.
     *
     * @param SimpleXMLElement $xml
     *
     * @return void
     */
    private function loadFromExtensions(SimpleXMLElement $xml)
    {
        foreach (dom_import_simplexml($xml)->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->namespaceURI === 'http://symfony.com/schema/dic/services') {
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
    static public function convertDomElementToArray(\DomElement $element)
    {
        $empty = true;
        $config = array();
        foreach ($element->attributes as $name => $node) {
            $config[$name] = SimpleXMLElement::phpize($node->value);
            $empty = false;
        }

        $nodeValue = false;
        foreach ($element->childNodes as $node) {
            if ($node instanceof \DOMText) {
                if (trim($node->nodeValue)) {
                    $nodeValue = trim($node->nodeValue);
                    $empty = false;
                }
            } elseif (!$node instanceof \DOMComment) {
                if ($node instanceof \DOMElement && '_services' === $node->nodeName) {
                    $value = new Reference($node->getAttribute('id'));
                } else {
                    $value = static::convertDomElementToArray($node);
                }

                $key = $node->localName;
                if (isset($config[$key])) {
                    if (!is_array($config[$key]) || !is_int(key($config[$key]))) {
                        $config[$key] = array($config[$key]);
                    }
                    $config[$key][] = $value;
                } else {
                    $config[$key] = $value;
                }

                $empty = false;
            }
        }

        if (false !== $nodeValue) {
            $value = SimpleXMLElement::phpize($nodeValue);
            if (count($config)) {
                $config['value'] = $value;
            } else {
                $config = $value;
            }
        }

        return !$empty ? $config : null;
    }
}
