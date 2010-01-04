<?php

namespace Symfony\Components\DependencyInjection\Loader;

use Symfony\Components\DependencyInjection\Definition;
use Symfony\Components\DependencyInjection\Reference;
use Symfony\Components\DependencyInjection\BuilderConfiguration;
use Symfony\Components\DependencyInjection\SimpleXMLElement;

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
 * @version    SVN: $Id: LoaderFileXml.php 267 2009-03-26 19:56:18Z fabien $
 */
class XmlFileLoader extends FileLoader
{
  /**
   * Loads an array of XML files.
   *
   * @param  array $files An array of XML files
   *
   * @return array An array of definitions and parameters
   */
  public function load($files)
  {
    if (!is_array($files))
    {
      $files = array($files);
    }

    return $this->parse($this->getFilesAsXml($files));
  }

  protected function parse(array $xmls)
  {
    $configuration = new BuilderConfiguration();

    foreach ($xmls as $file => $xml)
    {
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
    }

    return $configuration;
  }

  protected function parseParameters(BuilderConfiguration $configuration, $xml, $file)
  {
    if (!$xml->parameters)
    {
      return array();
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
    if (isset($import['class']) && $import['class'] != get_class($this))
    {
      $class = (string) $import['class'];
      $loader = new $class($this->paths);
    }
    else
    {
      $loader = $this;
    }

    $importedFile = $this->getAbsolutePath((string) $import['resource'], dirname($file));

    return $loader->load($importedFile);
  }

  protected function parseDefinitions(BuilderConfiguration $configuration, $xml, $file)
  {
    if (!$xml->services)
    {
      return array();
    }

    $definitions = array();
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
      $method = 'set'.ucfirst($key);
      if (isset($service[$key]))
      {
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

    $configuration->setDefinition($id, $definition);
  }

  protected function getFilesAsXml(array $files)
  {
    $xmls = array();
    foreach ($files as $file)
    {
      $path = $this->getAbsolutePath($file);

      if (!file_exists($path))
      {
        throw new \InvalidArgumentException(sprintf('The service file "%s" does not exist (in: %s).', $file, implode(', ', $this->paths)));
      }

      $dom = new \DOMDocument();
      libxml_use_internal_errors(true);
      if (!$dom->load(realpath($path), LIBXML_COMPACT))
      {
        throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
      }
      libxml_use_internal_errors(false);
      $this->validate($dom, $path);

      $xmls[$path] = simplexml_import_dom($dom, 'Symfony\Components\DependencyInjection\SimpleXMLElement');
    }

    return $xmls;
  }

  protected function processAnonymousServices(BuilderConfiguration $configuration, $xml, $file)
  {
    $definitions = array();
    $count = 0;

    // find anonymous service definitions
    $xml->registerXPathNamespace('container', 'http://symfony-project.org/2.0/container');
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
    libxml_use_internal_errors(true);
    if (!$dom->schemaValidate(__DIR__.'/services.xsd'))
    {
      throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
    }
    libxml_use_internal_errors(false);

    // validate extensions
    foreach ($dom->documentElement->childNodes as $node)
    {
      if (!$node instanceof \DOMElement || in_array($node->tagName, array('imports', 'parameters', 'services')))
      {
        continue;
      }

      // can it be handled by an extension?
      if (false !== strpos($node->tagName, ':'))
      {
        list($namespace, $tag) = explode(':', $node->tagName);
        if (!static::getExtension($namespace))
        {
          throw new \InvalidArgumentException(sprintf('There is no extension able to load the configuration for "%s" (in %s).', $node->tagName, $file));
        }

        continue;
      }

      throw new \InvalidArgumentException(sprintf('The "%s" tag is not valid (in %s).', $node->tagName, $file));
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
    foreach (dom_import_simplexml($xml)->getElementsByTagNameNS('*', '*') as $element)
    {
      if (!$element->prefix)
      {
        continue;
      }

      $values = static::convertDomElementToArray($element);
      $config = $this->getExtension($element->prefix)->load($element->localName, is_array($values) ? $values : array($values));

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
        $config[$node->tagName] = static::convertDomElementToArray($node);
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
