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
use Symfony\Component\DependencyInjection\SimpleXMLElement;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * XmlFileLoader loads XML files service definitions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class XmlFileLoader extends FileLoader
{
    /**
     * {@inheritdoc}
     */
    public function load($resource, $type = null)
    {
        $path = $this->locator->locate($resource);

        $xml = $this->parseFile($path);
        $xml->registerXPathNamespace('container', 'http://symfony.com/schema/dic/services');

        $this->container->addResource(new FileResource($path));

        // anonymous services
        $this->processAnonymousServices($xml, $path);

        // imports
        $this->parseImports($xml, $path);

        // parameters
        $this->parseParameters($xml);

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
     * @param SimpleXMLElement $xml
     */
    private function parseParameters(SimpleXMLElement $xml)
    {
        if (!$xml->parameters) {
            return;
        }

        $this->container->getParameterBag()->add($xml->parameters->getArgumentsAsPhp('parameter'));
    }

    /**
     * Parses imports.
     *
     * @param SimpleXMLElement $xml
     * @param string           $file
     */
    private function parseImports(SimpleXMLElement $xml, $file)
    {
        if (false === $imports = $xml->xpath('//container:imports/container:import')) {
            return;
        }

        $defaultDirectory = dirname($file);
        foreach ($imports as $import) {
            $this->setCurrentDir($defaultDirectory);
            $this->import((string) $import['resource'], null, (bool) $import->getAttributeAsPhp('ignore-errors'), $file);
        }
    }

    /**
     * Parses multiple definitions.
     *
     * @param SimpleXMLElement $xml
     * @param string           $file
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
     * Parses an individual Definition.
     *
     * @param string           $id
     * @param SimpleXMLElement $service
     * @param string           $file
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

        foreach (array('class', 'scope', 'public', 'factory-class', 'factory-method', 'factory-service', 'synthetic', 'synchronized', 'lazy', 'abstract') as $key) {
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

                if (false !== strpos($name, '-') && false === strpos($name, '_') && !array_key_exists($normalizedName = str_replace('-', '_', $name), $parameters)) {
                    $parameters[$normalizedName] = SimpleXMLElement::phpize($value);
                }
                // keep not normalized key for BC too
                $parameters[$name] = SimpleXMLElement::phpize($value);
            }

            if ('' === (string) $tag['name']) {
                throw new InvalidArgumentException(sprintf('The tag name for service "%s" in %s must be a non-empty string.', $id, $file));
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
     * @return SimpleXMLElement
     *
     * @throws InvalidArgumentException When loading of XML file returns error
     */
    protected function parseFile($file)
    {
        try {
            $dom = XmlUtils::loadFile($file, array($this, 'validateSchema'));
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException(sprintf('Unable to parse file "%s".', $file), $e->getCode(), $e);
        }

        $this->validateExtensions($dom, $file);

        return simplexml_import_dom($dom, 'Symfony\\Component\\DependencyInjection\\SimpleXMLElement');
    }

    /**
     * Processes anonymous services.
     *
     * @param SimpleXMLElement $xml
     * @param string           $file
     */
    private function processAnonymousServices(SimpleXMLElement $xml, $file)
    {
        $definitions = array();
        $count = 0;

        // anonymous services as arguments/properties
        if (false !== $nodes = $xml->xpath('//container:argument[@type="service"][not(@id)]|//container:property[@type="service"][not(@id)]')) {
            foreach ($nodes as $node) {
                // give it a unique name
                $node['id'] = sprintf('%s_%d', md5($file), ++$count);

                $definitions[(string) $node['id']] = array($node->service, $file, false);
                $node->service['id'] = (string) $node['id'];
            }
        }

        // anonymous services "in the wild"
        if (false !== $nodes = $xml->xpath('//container:services/container:service[not(@id)]')) {
            foreach ($nodes as $node) {
                // give it a unique name
                $node['id'] = sprintf('%s_%d', md5($file), ++$count);

                $definitions[(string) $node['id']] = array($node, $file, true);
                $node->service['id'] = (string) $node['id'];
            }
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
     * @param SimpleXMLElement $xml
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
    public static function convertDomElementToArray(\DomElement $element)
    {
        return XmlUtils::convertDomElementToArray($element);
    }
}
