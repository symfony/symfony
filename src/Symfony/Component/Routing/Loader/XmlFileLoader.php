<?php

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Resource\FileResource;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * XmlFileLoader loads XML routing files.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class XmlFileLoader extends FileLoader
{
    /**
     * Loads an XML file.
     *
     * @param string $file An XML file path
     * @param string $type The resource type
     *
     * @return RouteCollection A RouteCollection instance
     *
     * @throws \InvalidArgumentException When a tag can't be parsed
     */
    public function load($file, $type = null)
    {
        $path = $this->findFile($file);

        $xml = $this->loadFile($path);

        $collection = new RouteCollection();
        $collection->addResource(new FileResource($path));

        // process routes and imports
        foreach ($xml->documentElement->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            switch ($node->tagName) {
                case 'route':
                    $this->parseRoute($collection, $node, $path);
                    break;
                case 'import':
                    $resource = (string) $node->getAttribute('resource');
                    $type = (string) $node->getAttribute('type');
                    $prefix = (string) $node->getAttribute('prefix');
                    $this->currentDir = dirname($path);
                    $collection->addCollection($this->import($resource, $type), $prefix);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unable to parse tag "%s"', $node->tagName));
            }
        }

        return $collection;
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return boolean True if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'xml' === pathinfo($resource, PATHINFO_EXTENSION) && (!$type || 'xml' === $type);
    }

    /**
     * Parses a route and adds it to the RouteCollection.
     *
     * @param RouteCollection $collection A RouteCollection instance
     * @param \DOMElement     $definition Route definition
     * @param string          $file       An XML file path
     *
     * @throws \InvalidArgumentException When the definition cannot be parsed
     */
    protected function parseRoute(RouteCollection $collection, \DOMElement $definition, $file)
    {
        $defaults = array();
        $requirements = array();
        $options = array();

        foreach ($definition->childNodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            switch ($node->tagName) {
                case 'default':
                    $defaults[(string) $node->getAttribute('key')] = trim((string) $node->nodeValue);
                 break;
                case 'option':
                    $options[(string) $node->getAttribute('key')] = trim((string) $node->nodeValue);
                    break;
                case 'requirement':
                    $requirements[(string) $node->getAttribute('key')] = trim((string) $node->nodeValue);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unable to parse tag "%s"', $node->tagName));
            }
        }

        $route = new Route((string) $definition->getAttribute('pattern'), $defaults, $requirements, $options);

        $collection->add((string) $definition->getAttribute('id'), $route);
    }

    /**
     * Loads an XML file.
     *
     * @param string $file An XML file path
     *
     * @return \DOMDocument
     *
     * @throws \InvalidArgumentException When loading of XML file returns error
     */
    protected function loadFile($file)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->load($file, LIBXML_COMPACT)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        $dom->validateOnParse = true;
        $dom->normalizeDocument();
        libxml_use_internal_errors(false);
        $this->validate($dom);

        return $dom;
    }

    /**
     * Validates a loaded XML file.
     *
     * @param \DOMDocument $dom A loaded XML file
     *
     * @throws \InvalidArgumentException When XML doesn't validate its XSD schema
     */
    protected function validate(\DOMDocument $dom)
    {
        $parts = explode('/', str_replace('\\', '/', __DIR__.'/schema/routing/routing-1.0.xsd'));
        $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
        $location = 'file:///'.$drive.implode('/', $parts);

        $current = libxml_use_internal_errors(true);
        if (!$dom->schemaValidate($location)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        libxml_use_internal_errors($current);
    }

    /**
     * Retrieves libxml errors and clears them.
     *
     * @return array An array of libxml error strings
     */
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
}
