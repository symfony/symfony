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
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * XmlFileLoader loads XML files service definitions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class XmlFileLoader extends FileLoader
{
    const NAMESPACE_URI = 'http://symfony.com/schema/dic/services';

    /**
     * Loads an XML file.
     *
     * @param mixed  $file The resource
     * @param string $type The resource type
     */
    public function load($file, $type = null)
    {
        $path = $this->locator->locate($file);

        $dom = $this->parseFile($path);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('container', self::NAMESPACE_URI);

        $element = $dom->documentElement;

        $this->container->addResource(new FileResource($path));

        // anonymous services
        $this->processAnonymousServices($element, $xpath, $path);

        // imports
        $this->parseImports($element, $xpath, $path);

        // parameters
        $this->parseParameters($element, $xpath, $path);

        // extensions
        $this->loadFromExtensions($element, $xpath);

        // services
        $this->parseDefinitions($element, $xpath, $path);
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
        $empty = true;
        $config = array();
        foreach ($element->attributes as $name => $node) {
            $config[$name] = static::phpize($node->value);
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
            $value = static::phpize($nodeValue);
            if (count($config)) {
                $config['value'] = $value;
            } else {
                $config = $value;
            }
        }

        return !$empty ? $config : null;
    }

    /**
     * Returns arguments as valid php types.
     *
     * @param string  $name
     * @param Boolean $lowercase
     *
     * @return mixed
     */
    public static function getArgumentsAsPhp(\DOMElement $element, $name, $lowercase = true)
    {
        $arguments = array();

        foreach ($element->childNodes as $arg) {
            if (!$arg instanceof \DOMElement || $arg->namespaceURI !== self::NAMESPACE_URI || $arg->localName !== $name) {
                continue;
            }

            if ($arg->hasAttribute('name')) {
                $arg->setAttribute('key', $arg->getAttribute('name'));
            }
            $key = $arg->hasAttribute('key') ? $arg->getAttribute('key') : (!$arguments ? 0 : max(array_keys($arguments)) + 1);

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
                    $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE;
                    if ($arg->hasAttribute('on-invalid') && 'ignore' == $arg->getAttribute('on-invalid')) {
                        $invalidBehavior = ContainerInterface::IGNORE_ON_INVALID_REFERENCE;
                    } elseif ($arg->hasAttribute('on-invalid') && 'null' == $arg->getAttribute('on-invalid')) {
                        $invalidBehavior = ContainerInterface::NULL_ON_INVALID_REFERENCE;
                    }

                    if ($arg->hasAttribute('strict')) {
                        $strict = self::phpize($arg->getAttribute('strict'));
                    } else {
                        $strict = true;
                    }

                    $arguments[$key] = new Reference($arg->getAttribute('id'), $invalidBehavior, $strict);
                    break;
                case 'collection':
                    $arguments[$key] = static::getArgumentsAsPhp($arg, $name, false);
                    break;
                case 'string':
                    $arguments[$key] = $arg->nodeValue;
                    break;
                case 'constant':
                    $arguments[$key] = constant($arg->nodeValue);
                    break;
                default:
                    $arguments[$key] = self::phpize($arg->nodeValue);
            }
        }

        return $arguments;
    }

    /**
     * Converts an xml value to a php type.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function phpize($value)
    {
        $value = (string) $value;
        $lowercaseValue = strtolower($value);

        switch (true) {
            case 'null' === $lowercaseValue:
                return null;
            case ctype_digit($value):
                $raw = $value;
                $cast = intval($value);

                return '0' == $value[0] ? octdec($value) : (((string) $raw == (string) $cast) ? $cast : $raw);
            case 'true' === $lowercaseValue:
                return true;
            case 'false' === $lowercaseValue:
                return false;
            case is_numeric($value):
                return '0x' == $value[0].$value[1] ? hexdec($value) : floatval($value);
            case preg_match('/^(-|\+)?[0-9,]+(\.[0-9]+)?$/', $value):
                return floatval(str_replace(',', '', $value));
            default:
                return $value;
        }
    }

    /**
     * Parses parameters
     *
     * @param \DOMElement $xml
     * @param \DOMXPath   $xpath
     * @param string      $file
     */
    private function parseParameters(\DOMElement $xml, \DOMXPath $xpath, $file)
    {
        $parameters = $xpath->query('//container:parameters');
        if (!$parameters->length) {
            return;
        }

        $this->container->getParameterBag()->add(static::getArgumentsAsPhp($parameters->item(0), 'parameter'));
    }

    /**
     * Parses imports
     *
     * @param \DOMElement $xml
     * @param \DOMXPath   $xpath
     * @param string      $file
     */
    private function parseImports(\DOMElement $xml, \DOMXPath $xpath, $file)
    {
        $imports = $xpath->query('//container:imports/container:import');
        if (!$imports->length) {
            return;
        }

        foreach ($imports as $import) {
            $this->setCurrentDir(dirname($file));
            $resource = $import->getAttribute('resource');
            $this->import($resource, null, (Boolean) $this->getAttributeAsPhp($import, 'ignore-errors', $file));
        }
    }

    /**
     * Parses multiple definitions
     *
     * @param \DOMElement $xml
     * @param \DOMXPath   $xpath
     * @param string      $file
     */
    private function parseDefinitions(\DOMElement $xml, \DOMXPath $xpath, $file)
    {
        $services = $xpath->query('//container:services/container:service');
        if (!$services->length) {
            return;
        }

        foreach ($services as $service) {
            $this->parseDefinition($service->getAttribute('id'), $service, $file);
        }
    }

    /**
     * Parses an individual Definition
     *
     * @param string      $id
     * @param \DOMElement $service
     * @param string      $file
     */
    private function parseDefinition($id, \DOMElement $service, $file)
    {
        if ($service->hasAttribute('alias')) {
            $public = true;
            if ($service->hasAttribute('public')) {
                $public = $this->getAttributeAsPhp($service, 'public');
            }
            $this->container->setAlias($id, new Alias($service->getAttribute('alias'), $public));

            return;
        }

        if ($service->hasAttribute('parent')) {
            $definition = new DefinitionDecorator($service->getAttribute('parent'));
        } else {
            $definition = new Definition();
        }

        foreach (array('class', 'scope', 'public', 'factory-class', 'factory-method', 'factory-service', 'synthetic', 'abstract') as $key) {
            if ($service->hasAttribute($key)) {
                $method = 'set'.str_replace('-', '', $key);
                $definition->$method($this->getAttributeAsPhp($service, $key));
            }
        }

        $definition->setArguments(static::getArgumentsAsPhp($service, 'argument'));
        $definition->setProperties(static::getArgumentsAsPhp($service, 'property'));

        foreach ($service->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->namespaceURI !== self::NAMESPACE_URI) {
                continue;
            }

            switch ($node->localName) {
                case 'file':
                    $definition->setFile($node->nodeValue);
                    break;
                case 'configurator':
                    if ($node->hasAttribute('function')) {
                        $definition->setConfigurator($node->getAttribute('function'));
                    } else {
                        if ($node->hasAttribute('service')) {
                            $class = new Reference($node->getAttribute('service'), ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, false);
                        } else {
                            $class = $node->getAttribute('class');
                        }

                        $definition->setConfigurator(array($class, $node->getAttribute('method')));
                    }
                    break;
                case 'call':
                    $definition->addMethodCall($node->getAttribute('method'), static::getArgumentsAsPhp($node, 'argument'));
                    break;
                case 'tag':
                    $parameters = array();
                    foreach ($node->attributes as $attribute) {
                        $name = $attribute->name;
                        if ('name' === $name) {
                            continue;
                        }

                        $parameters[$name] = static::phpize($attribute->value);
                    }

                    $definition->addTag($node->getAttribute('name'), $parameters);
                    break;
            }
        }

        $this->container->setDefinition($id, $definition);
    }

    /**
     * Parses a XML file.
     *
     * @param string $file Path to a file
     *
     * @throws InvalidArgumentException When loading of XML file returns error
     *
     * @return \DOMDocument
     */
    protected function parseFile($file)
    {
        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->validateOnParse = true;
        if (!$dom->loadXML(file_get_contents($file), LIBXML_NONET | (defined('LIBXML_COMPACT') ? LIBXML_COMPACT : 0))) {
            libxml_disable_entity_loader($disableEntities);

            throw new InvalidArgumentException(implode("\n", $this->getXmlErrors($internalErrors)));
        }
        $dom->normalizeDocument();

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        foreach ($dom->childNodes as $child) {
            if ($child->nodeType === XML_DOCUMENT_TYPE_NODE) {
                throw new InvalidArgumentException('Document types are not allowed.');
            }
        }

        $this->validate($dom, $file);

        return $dom;
    }

    /**
     * Processes anonymous services
     *
     * @param \DOMElement $xml
     * @param \DOMXpath   $xpath
     * @param string      $file
     */
    private function processAnonymousServices(\DOMElement $xml, \DOMXPath $xpath, $file)
    {
        $definitions = array();
        $count = 0;

        // anonymous services as arguments/properties
        $nodes = $xpath->query('//container:argument[@type="service"][not(@id)]|//container:property[@type="service"][not(@id)]');
        if ($nodes->length) {
            foreach ($nodes as $node) {
                // give it a unique name
                $node->setAttribute('id', sprintf('%s_%d', md5($file), ++$count));

                $service = $xpath->query('container:service', $node)->item(0);
                $definitions[$node->getAttribute('id')] = array($service, $file, false);
                $service->setAttribute('id', $node->getAttribute('id'));
            }
        }

        // anonymous services "in the wild"
        $nodes = $xpath->query('//container:services/container:service[not(@id)]');
        if ($nodes->length) {
            foreach ($nodes as $node) {
                // give it a unique name
                $node->setAttribute('id', sprintf('%s_%d', md5($file), ++$count));

                $definitions[$node->getAttribute('id')] = array($node, $file, true);
            }
        }

        // resolve definitions
        krsort($definitions);
        foreach ($definitions as $id => $def) {
            // anonymous services are always private
            $def[0]->setAttribute('public', false);

            $this->parseDefinition($id, $def[0], $def[1]);

            $oNode = $def[0];
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
     * Validates an XML document.
     *
     * @param \DOMDocument $dom
     * @param string       $file
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
     * @param string       $file
     *
     * @throws RuntimeException         When extension references a non-existent XSD file
     * @throws InvalidArgumentException When xml doesn't validate its xsd schema
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

        $current = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valid = @$dom->schemaValidateSource($source);

        foreach ($tmpfiles as $tmpfile) {
            @unlink($tmpfile);
        }
        if (!$valid) {
            throw new InvalidArgumentException(implode("\n", $this->getXmlErrors($current)));
        }
        libxml_use_internal_errors($current);
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
     * Returns an array of XML errors.
     *
     * @param Boolean $internalErrors
     *
     * @return array
     */
    private function getXmlErrors($internalErrors)
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
        libxml_use_internal_errors($internalErrors);

        return $errors;
    }

    /**
     * Loads from an extension.
     *
     * @param \DOMElement $xml
     */
    private function loadFromExtensions(\DOMElement $xml)
    {
        foreach ($xml->childNodes as $node) {
            if (!$node instanceof \DOMElement || $node->namespaceURI === self::NAMESPACE_URI) {
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
     * Converts an attribute as a php type.
     *
     * @param string $name
     *
     * @return mixed
     */
    private function getAttributeAsPhp(\DOMElement $element, $name)
    {
        return static::phpize($element->getAttribute($name));
    }
}
