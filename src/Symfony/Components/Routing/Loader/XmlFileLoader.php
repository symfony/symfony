<?php

namespace Symfony\Components\Routing\Loader;

use Symfony\Components\Routing\RouteCollection;
use Symfony\Components\Routing\Route;
use Symfony\Components\Routing\Resource\FileResource;

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
 * @package    Symfony
 * @subpackage Components_Routing
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
                    $this->parseImport($collection, $node, $path);
                    break;
                default:
                    throw new \InvalidArgumentException(sprintf('Unable to parse tag "%s"', $node->tagName));
            }
        }

        return $collection;
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

    protected function parseImport(RouteCollection $collection, $node, $file)
    {
        $class = null;
        if ($node->hasAttribute('class') && $import->getAttribute('class') !== get_class($this)) {
            $class = (string) $node->getAttribute('class');
        } else {
            // try to detect loader with the extension
            switch (pathinfo((string) $node->getAttribute('resource'), PATHINFO_EXTENSION)) {
                case 'yml':
                    $class = 'Symfony\\Components\\Routing\\Loader\\YamlFileLoader';
                    break;
            }
        }

        $loader = null === $class ? $this : new $class($this->paths);

        $importedFile = $this->getAbsolutePath((string) $node->getAttribute('resource'), dirname($file));

        $collection->addCollection($loader->load($importedFile), (string) $node->getAttribute('prefix'));
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
        $drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts) : '';
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
