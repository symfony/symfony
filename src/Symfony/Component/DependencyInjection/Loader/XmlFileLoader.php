<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\DefinitionDecorator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\InterfaceInjector;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\SimpleXMLElement;
use Symfony\Component\DependencyInjection\Resource\FileResource;

/**
 * XmlFileLoader loads XML files service definitions.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class XmlFileLoader extends FileLoader
{
    /**
     * Loads an XML file.
     *
     * @param mixed $resource The resource
     */
    public function load($file)
    {
        $path = $this->findFile($file);

        $xml = $this->parseFile($path);

        $this->container->addResource(new FileResource($path));

        // anonymous services
        $xml = $this->processAnonymousServices($xml, $file);

        // imports
        $this->parseImports($xml, $file);

        // parameters
        $this->parseParameters($xml, $file);

        // extensions
        $this->loadFromExtensions($xml);

        // interface injectors
        $this->parseInterfaceInjectors($xml, $file);

        // services
        $this->parseDefinitions($xml, $file);
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param  mixed $resource A resource
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource)
    {
        return is_string($resource) && 'xml' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    protected function parseParameters(SimpleXMLElement $xml, $file)
    {
        if (!$xml->parameters) {
            return;
        }

        $this->container->getParameterBag()->add($xml->parameters->getArgumentsAsPhp('parameter'));
    }

    protected function parseImports(SimpleXMLElement $xml, $file)
    {
        if (!$xml->imports) {
            return;
        }

        foreach ($xml->imports->import as $import) {
            $this->currentDir = dirname($file);
            $this->import((string) $import['resource'], (Boolean) $import->getAttributeAsPhp('ignore-errors'));
        }
    }

    protected function parseInterfaceInjectors(SimpleXMLElement $xml, $file)
    {
        if (!$xml->interfaces) {
            return;
        }

        foreach ($xml->interfaces->interface as $interface) {
            $this->parseInterfaceInjector((string) $interface['class'], $interface, $file);
        }
    }

    protected function parseInterfaceInjector($class, $interface, $file)
    {
        $injector = new InterfaceInjector($class);
        foreach ($interface->call as $call) {
            $injector->addMethodCall((string) $call['method'], $call->getArgumentsAsPhp('argument'));
        }
        $this->container->addInterfaceInjector($injector);
    }

    protected function parseDefinitions(SimpleXMLElement $xml, $file)
    {
        if (!$xml->services) {
            return;
        }

        foreach ($xml->services->service as $service) {
            $this->parseDefinition((string) $service['id'], $service, $file);
        }
    }

    protected function parseDefinition($id, $service, $file)
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
            $definition = new DefinitionDecorator($service['parent']);
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
     * @throws \InvalidArgumentException When loading of XML file returns error
     */
    protected function parseFile($file)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->load($file, LIBXML_COMPACT)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        $dom->validateOnParse = true;
        $dom->normalizeDocument();
        libxml_use_internal_errors(false);
        $this->validate($dom, $file);

        return simplexml_import_dom($dom, 'Symfony\\Component\\DependencyInjection\\SimpleXMLElement');
    }

    protected function processAnonymousServices(SimpleXMLElement $xml, $file)
    {
        $definitions = array();
        $count = 0;

        // find anonymous service definitions
        $xml->registerXPathNamespace('container', 'http://www.symfony-project.org/schema/dic/services');

        // anonymous services as arguments
        $nodes = $xml->xpath('//container:argument[@type="service"][not(@id)]');
        foreach ($nodes as $node) {
            // give it a unique name
            $node['id'] = sprintf('%s_%d', md5($file), ++$count);

            $definitions[(string) $node['id']] = array($node->service, $file, false);
            $node->service['id'] = (string) $node['id'];
        }

        // anonymous services "in the wild"
        $nodes = $xml->xpath('//container:service[not(@id)]');
        foreach ($nodes as $node) {
            // give it a unique name
            $node['id'] = sprintf('%s_%d', md5($file), ++$count);

            $definitions[(string) $node['id']] = array($node, $file, true);
            $node->service['id'] = (string) $node['id'];
        }

        // resolve definitions
        krsort($definitions);
        foreach ($definitions as $id => $def) {
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

    protected function validate(\DOMDocument $dom, $file)
    {
        $this->validateSchema($dom, $file);
        $this->validateExtensions($dom, $file);
    }

    /**
     * @throws \RuntimeException         When extension references a non-existent XSD file
     * @throws \InvalidArgumentException When xml doesn't validate its xsd schema
     */
    protected function validateSchema(\DOMDocument $dom, $file)
    {
        $schemaLocations = array('http://www.symfony-project.org/schema/dic/services' => str_replace('\\', '/', __DIR__.'/schema/dic/services/services-1.0.xsd'));

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
            if (preg_match('#^phar://#i', $location)) {
                $tmpfile = tempnam(sys_get_temp_dir(), 'sf2');
                if ($tmpfile) {
                    file_put_contents($tmpfile, file_get_contents($location));
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
<xsd:schema xmlns="http://www.symfony-project.org/schema"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    targetNamespace="http://www.symfony-project.org/schema"
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
     * @throws  \InvalidArgumentException When non valid tag are found or no extension are found
     */
    protected function validateExtensions(\DOMDocument $dom, $file)
    {
        foreach ($dom->documentElement->childNodes as $node) {
            if (!$node instanceof \DOMElement || in_array($node->tagName, array('imports', 'parameters', 'services', 'interfaces'))) {
                continue;
            }

            // can it be handled by an extension?
            if (!$this->container->hasExtension($node->namespaceURI)) {
                throw new \InvalidArgumentException(sprintf('There is no extension able to load the configuration for "%s" (in %s).', $node->tagName, $file));
            }
        }
    }

    protected function getXmlErrors()
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

    protected function loadFromExtensions(SimpleXMLElement $xml)
    {
        foreach (dom_import_simplexml($xml)->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->namespaceURI === 'http://www.symfony-project.org/schema/dic/services') {
                continue;
            }

            $values = static::convertDomElementToArray($node);
            if (!is_array($values)) {
                $values = array();
            }

            $this->container->loadFromExtension($node->namespaceURI, $node->localName, $values);
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
