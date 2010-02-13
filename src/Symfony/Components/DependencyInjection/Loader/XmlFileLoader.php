<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\SimpleXMLElement;
use Symfony\Components\DependencyInjection\FileResource;

/*
 * This file is part of the symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * XmlFileLoader loads XML files service definitions.
 *
 * @package    symfony
 * @subpackage dependency_injection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class XmlFileLoader extends FileLoader
{
  /**
   * Loads an array of XML files.
   *
   * @param  string $file An XML file path
   *
   * @return BuilderConfiguration A BuilderConfiguration instance
   */
  public function load($file)
  {
    $path = $this->findFile($file);

    $xml = $this->parseFile($path);

    $configuration = new BuilderConfiguration();

    $configuration->addResource(new FileResource($path));

    // anonymous services
    $xml = $this->processAnonymousServices($configuration, $xml, $file);

    // imports
    $this->parseImports($configuration, $xml, $file);

    // parameters
    $this->parseParameters($configuration, $xml, $file);

    // services
    $this->parseDefinitions($configuration, $xml, $file);

    // extensions
    $this->loadFromExtensions($configuration, $xml);

    return $configuration;
  }

  protected function parseParameters(BuilderConfiguration $configuration, $xml, $file)
  {
    if (!$xml->parameters)
    {
      return;
    }

    $configuration->addParameters($xml->parameters->getArgumentsAsPhp('parameter'));
  }

  protected function parseImports(BuilderConfiguration $configuration, $xml, $file)
  {
    if (!$xml->imports)
    {
      return;
    }

    foreach ($xml->imports->import as $import)
    {
      $configuration->merge($this->parseImport($import, $file));
    }
  }

  protected function parseImport($import, $file)
  {
    $class = null;
    if (isset($import['class']) && $import['class'] !== get_class($this))
    {
      $class = (string) $import['class'];
    }
    else
    {
      // try to detect loader with the extension
      switch (pathinfo((string) $import['resource'], PATHINFO_EXTENSION))
      {
        case 'yml':
          $class = 'Symfony\\Components\\DependencyInjection\\Loader\\YamlFileLoader';
          break;
        case 'ini':
          $class = 'Symfony\\Components\\DependencyInjection\\Loader\\IniFileLoader';
          break;
      }
    }

    $loader = null === $class ? $this : new $class($this->paths);

    $importedFile = $this->getAbsolutePath((string) $import['resource'], dirname($file));

    return $loader->load($importedFile);
  }

  protected function parseDefinitions(BuilderConfiguration $configuration, $xml, $file)
  {
    if (!$xml->services)
    {
      return;
    }

    foreach ($xml->services->service as $service)
    {
      $this->parseDefinition($configuration, (string) $service['id'], $service, $file);
    }
  }

  protected function parseDefinition(BuilderConfiguration $configuration, $id, $service, $file)
  {
    if ((string) $service['alias'])
    {
      $configuration->setAlias($id, (string) $service['alias']);

      return;
    }

    $definition = new Definition((string) $service['class']);

    foreach (array('shared', 'constructor') as $key)
    {
      if (isset($service[$key]))
      {
        $method = 'set'.ucfirst($key);
        $definition->$method((string) $service->getAttributeAsPhp($key));
      }
    }

    if ($service->file)
    {
      $definition->setFile((string) $service->file);
    }

    $definition->setArguments($service->getArgumentsAsPhp('argument'));

    if (isset($service->configurator))
    {
      if (isset($service->configurator['function']))
      {
        $definition->setConfigurator((string) $service->configurator['function']);
      }
      else
      {
        if (isset($service->configurator['service']))
        {
          $class = new Reference((string) $service->configurator['service']);
        }
        else
        {
          $class = (string) $service->configurator['class'];
        }

        $definition->setConfigurator(array($class, (string) $service->configurator['method']));
      }
    }

    foreach ($service->call as $call)
    {
      $definition->addMethodCall((string) $call['method'], $call->getArgumentsAsPhp('argument'));
    }

    foreach ($service->annotation as $annotation)
    {
      $parameters = array();
      foreach ($annotation->attributes() as $name => $value)
      {
        if ('name' === $name)
        {
          continue;
        }

        $parameters[$name] = SimpleXMLElement::phpize($value);
      }

      $definition->addAnnotation((string) $annotation['name'], $parameters);
    }

    $configuration->setDefinition($id, $definition);
  }

  protected function parseFile($file)
  {
    $dom = new \DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->load($file, LIBXML_COMPACT))
    {
      throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
    }
    $dom->validateOnParse = true;
    $dom->normalizeDocument();
    libxml_use_internal_errors(false);
    $this->validate($dom, $file);

    return simplexml_import_dom($dom, 'Symfony\\Components\\DependencyInjection\\SimpleXMLElement');
  }

  protected function processAnonymousServices(BuilderConfiguration $configuration, $xml, $file)
  {
    $definitions = array();
    $count = 0;

    // find anonymous service definitions
    $xml->registerXPathNamespace('container', 'http://www.symfony-project.org/schema/dic/services');
    $nodes = $xml->xpath('//container:argument[@type="service"][not(@id)]');
    foreach ($nodes as $node)
    {
      // give it a unique names
      $node['id'] = sprintf('_%s_%d', md5($file), ++$count);

      $definitions[(string) $node['id']] = array($node->service, $file);
      $node->service['id'] = (string) $node['id'];
    }

    // resolve definitions
    krsort($definitions);
    foreach ($definitions as $id => $def)
    {
      $this->parseDefinition($configuration, $id, $def[0], $def[1]);

      $oNode = dom_import_simplexml($def[0]);
      $oNode->parentNode->removeChild($oNode);
    }

    return $xml;
  }

  protected function validate($dom, $file)
  {
    $this->validateSchema($dom, $file);
    $this->validateExtensions($dom, $file);
  }

  protected function validateSchema($dom, $file)
  {
    $schemaLocations = array('http://www.symfony-project.org/schema/dic/services' => str_replace('\\', '/', __DIR__.'/schema/dic/services/services-1.0.xsd'));

    if ($element = $dom->documentElement->getAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation'))
    {
      $items = preg_split('/\s+/', $element);
      for ($i = 0, $nb = count($items); $i < $nb; $i += 2)
      {
        if ($extension = static::getExtension($items[$i]))
        {
          $path = str_replace('http://www.symfony-project.org/', str_replace('\\', '/', $extension->getXsdValidationBasePath()).'/', $items[$i + 1]);

          if (!file_exists($path))
          {
            throw new \RuntimeException(sprintf('Extension "%s" references a non-existent XSD file "%s"', get_class($extension), $path));
          }

          $schemaLocations[$items[$i]] = $path;
        }
      }
    }

    $imports = '';
    foreach ($schemaLocations as $namespace => $location)
    {
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

    libxml_use_internal_errors(true);
    if (!$dom->schemaValidateSource($source))
    {
      throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
    }
    libxml_use_internal_errors(false);
  }

  protected function validateExtensions($dom, $file)
  {
    foreach ($dom->documentElement->childNodes as $node)
    {
      if (!$node instanceof \DOMElement || in_array($node->tagName, array('imports', 'parameters', 'services')))
      {
        continue;
      }

      if ($node->namespaceURI === 'http://www.symfony-project.org/schema/dic/services')
      {
        throw new \InvalidArgumentException(sprintf('The "%s" tag is not valid (in %s).', $node->tagName, $file));
      }

      // can it be handled by an extension?
      if (!static::getExtension($node->namespaceURI))
      {
        throw new \InvalidArgumentException(sprintf('There is no extension able to load the configuration for "%s" (in %s).', $node->tagName, $file));
      }
    }
  }

  protected function getXmlErrors()
  {
    $errors = array();
    foreach (libxml_get_errors() as $error)
    {
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

  protected function loadFromExtensions(BuilderConfiguration $configuration, $xml)
  {
    foreach (dom_import_simplexml($xml)->childNodes as $node)
    {
      if (!$node instanceof \DOMElement || $node->namespaceURI === 'http://www.symfony-project.org/schema/dic/services')
      {
        continue;
      }

      $values = static::convertDomElementToArray($node);
      $config = $this->getExtension($node->namespaceURI)->load($node->localName, is_array($values) ? $values : array($values));

      $r = new \ReflectionObject($this->getExtension($node->namespaceURI));
      $config->addResource(new FileResource($r->getFileName()));

      $configuration->merge($config);
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
    foreach ($element->attributes as $name => $node)
    {
      $config[$name] = SimpleXMLElement::phpize($node->value);
      $empty = false;
    }

    $nodeValue = false;
    foreach ($element->childNodes as $node)
    {
      if ($node instanceof \DOMText)
      {
        if (trim($node->nodeValue))
        {
          $nodeValue = trim($node->nodeValue);
          $empty = false;
        }
      }
      elseif (!$node instanceof \DOMComment)
      {
        if (isset($config[$node->localName]))
        {
          if (!is_array($config[$node->localName]))
          {
            $config[$node->localName] = array($config[$node->localName]);
          }
          $config[$node->localName][] = static::convertDomElementToArray($node);
        }
        else
        {
          $config[$node->localName] = static::convertDomElementToArray($node);
        }

        $empty = false;
      }
    }

    if (false !== $nodeValue)
    {
      $value = SimpleXMLElement::phpize($nodeValue);
      if (count($config))
      {
        $config['value'] = $value;
      }
      else
      {
        $config = $value;
      }
    }

    return !$empty ? $config : null;
  }
}
