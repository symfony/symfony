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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class XmlFileLoader extends FileLoader
{
    /**
     * Loads an XML file.
     *
     * @param  string $file A XML file path
     *
     * @return RouteCollection A RouteCollection instance
     *
     * @throws \InvalidArgumentException When a tag can't be parsed
     */
    public function load($file)
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
                    $prefix = (string) $node->getAttribute('prefix');
                    $this->currentDir = dirname($path);
                    $collection->addCollection($this->import($resource), $prefix);
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
     * @param  mixed $resource A resource
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource)
    {
        return is_string($resource) && 'xml' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    protected function parseRoute(RouteCollection $collection, $definition, $file)
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

        $collection->addRoute((string) $definition->getAttribute('id'), $route);
    }

    /**
     * @throws \InvalidArgumentException When loading of XML file returns error
     */
    protected function loadFile($path)
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->load($path, LIBXML_COMPACT)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        $dom->validateOnParse = true;
        $dom->normalizeDocument();
        libxml_use_internal_errors(false);
        $this->validate($dom, $path);

        return $dom;
    }

    /**
     * @throws \InvalidArgumentException When xml doesn't validate its xsd schema
     */
    protected function validate($dom, $file)
    {
        $parts = explode('/', str_replace('\\', '/', __DIR__.'/schema/routing/routing-1.0.xsd'));
        $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
        $location = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));

        $current = libxml_use_internal_errors(true);
        if (!$dom->schemaValidate($location)) {
            throw new \InvalidArgumentException(implode("\n", $this->getXmlErrors()));
        }
        libxml_use_internal_errors($current);
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
}
