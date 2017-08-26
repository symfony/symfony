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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Config\Loader\FileLoader as BaseFileLoader;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Config\Resource\GlobResource;

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class FileLoader extends BaseFileLoader
{
    protected $container;
    protected $isLoadingInstanceof = false;
    protected $instanceof = array();

    /**
     * @param ContainerBuilder     $container A ContainerBuilder instance
     * @param FileLocatorInterface $locator   A FileLocator instance
     */
    public function __construct(ContainerBuilder $container, FileLocatorInterface $locator)
    {
        $this->container = $container;

        parent::__construct($locator);
    }

    /**
     * Registers a set of classes as services using PSR-4 for discovery.
     *
     * @param Definition $prototype A definition to use as template
     * @param string     $namespace The namespace prefix of classes in the scanned directory
     * @param string     $resource  The directory to look for classes, glob-patterns allowed
     * @param string     $exclude   A globed path of files to exclude
     */
    public function registerClasses(Definition $prototype, $namespace, $resource, $exclude = null)
    {
        $classes = $this->findClasses($namespace, $resource, $exclude);
        // prepare for deep cloning
        $prototype = serialize($prototype);

        foreach ($classes as $class) {
            $this->setDefinition($class, unserialize($prototype));
        }
    }

    /**
     * Registers a definition in the container with its instanceof-conditionals.
     *
     * @param string     $id
     * @param Definition $definition
     */
    protected function setDefinition($id, Definition $definition)
    {
        if ($this->isLoadingInstanceof) {
            if (!$definition instanceof ChildDefinition) {
                throw new InvalidArgumentException(sprintf('Invalid type definition "%s": ChildDefinition expected, "%s" given.', $id, get_class($definition)));
            }
            $this->instanceof[$id] = $definition;
        } else {
            $this->container->setDefinition($id, $definition instanceof ChildDefinition ? $definition : $definition->setInstanceofConditionals($this->instanceof));
        }
    }

    private function findClasses($namespace, $pattern, $excludePattern)
    {
        $parameterBag = $this->container->getParameterBag();

        $excludePaths = array();
        $excludePrefix = null;
        if ($excludePattern) {
            $excludePattern = $parameterBag->unescapeValue($parameterBag->resolveValue($excludePattern));
            foreach ($this->glob($excludePattern, true, $resource) as $path => $info) {
                if (null === $excludePrefix) {
                    $excludePrefix = $resource->getPrefix();
                }

                // normalize Windows slashes
                $excludePaths[str_replace('\\', '/', $path)] = true;
            }
        }

        $pattern = $parameterBag->unescapeValue($parameterBag->resolveValue($pattern));
        $classes = array();
        $extRegexp = '/\\.php$/';
        $prefixLen = null;
        foreach ($this->glob($pattern, true, $resource) as $path => $info) {
            if (null === $prefixLen) {
                $prefixLen = strlen($resource->getPrefix());

                if ($excludePrefix && strpos($excludePrefix, $resource->getPrefix()) !== 0) {
                    throw new InvalidArgumentException(sprintf('Invalid "exclude" pattern when importing classes for "%s": make sure your "exclude" pattern (%s) is a subset of the "resource" pattern (%s)', $namespace, $excludePattern, $pattern));
                }
            }

            if (isset($excludePaths[str_replace('\\', '/', $path)])) {
                continue;
            }

            if (!preg_match($extRegexp, $path, $m) || !$info->isReadable()) {
                continue;
            }

            if (!$class = $this->getClassFromFile($path)) {
                // no class found in file
                continue;
            }

            $r = $this->container->getReflectionClass($class);

            if (!$r->isInterface() && !$r->isTrait() && !$r->isAbstract()) {
                $classes[] = $class;
            }
        }

        // track only for new & removed files
        if ($resource instanceof GlobResource) {
            $this->container->addResource($resource);
        } else {
            foreach ($resource as $path) {
                $this->container->fileExists($path, false);
            }
        }

        return $classes;
    }

    /**
     * @param string $path
     *
     * @return string|null
     *
     * @see http://jarretbyrne.com/2015/06/197/
     */
    private function getClassFromFile($path)
    {
        $contents = file_get_contents($path);
        $class = '';
        $parsingNamespace = false;
        $parsingClass = false;

        foreach (token_get_all($contents) as $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $parsingNamespace = true;
            }

            if (is_array($token) && $token[0] === T_CLASS) {
                $parsingClass = true;
            }

            if ($parsingNamespace) {
                if (is_array($token) && in_array($token[0], array(T_STRING, T_NS_SEPARATOR), true)) {
                    $class .= $token[1];
                }

                if ($token === ';') {
                    $parsingNamespace = false;
                }
            }

            if ($parsingClass && is_array($token) && $token[0] === T_STRING) {
                $class .= '\\'.$token[1];
                $parsingClass = false;
            }
        }

        return class_exists($class) ? $class : null;
    }
}
