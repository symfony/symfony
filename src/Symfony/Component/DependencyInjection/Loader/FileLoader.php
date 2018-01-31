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
        if ('\\' !== substr($namespace, -1)) {
            throw new InvalidArgumentException(sprintf('Namespace prefix must end with a "\\": %s.', $namespace));
        }
        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+\\\\)++$/', $namespace)) {
            throw new InvalidArgumentException(sprintf('Namespace is not a valid PSR-4 prefix: %s.', $namespace));
        }

        $classes = $this->findClasses($namespace, $resource, $exclude);
        // prepare for deep cloning
        $serializedPrototype = serialize($prototype);
        $interfaces = array();
        $singlyImplemented = array();

        foreach ($classes as $class => $errorMessage) {
            if (interface_exists($class, false)) {
                $interfaces[] = $class;
            } else {
                $this->setDefinition($class, $definition = unserialize($serializedPrototype));
                if (null !== $errorMessage) {
                    $definition->addError($errorMessage);

                    continue;
                }
                foreach (class_implements($class, false) as $interface) {
                    $singlyImplemented[$interface] = isset($singlyImplemented[$interface]) ? false : $class;
                }
            }
        }
        foreach ($interfaces as $interface) {
            if (!empty($singlyImplemented[$interface])) {
                $this->container->setAlias($interface, $singlyImplemented[$interface])
                    ->setPublic(false);
            }
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

                if ($excludePrefix && 0 !== strpos($excludePrefix, $resource->getPrefix())) {
                    throw new InvalidArgumentException(sprintf('Invalid "exclude" pattern when importing classes for "%s": make sure your "exclude" pattern (%s) is a subset of the "resource" pattern (%s)', $namespace, $excludePattern, $pattern));
                }
            }

            if (isset($excludePaths[str_replace('\\', '/', $path)])) {
                continue;
            }

            if (!preg_match($extRegexp, $path, $m) || !$info->isReadable()) {
                continue;
            }
            $class = $namespace.ltrim(str_replace('/', '\\', substr($path, $prefixLen, -strlen($m[0]))), '\\');

            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+$/', $class)) {
                continue;
            }

            try {
                $r = $this->container->getReflectionClass($class);
            } catch (\ReflectionException $e) {
                $classes[$class] = sprintf(
                    'While discovering services from namespace "%s", an error was thrown when processing the class "%s": "%s".',
                    $namespace,
                    $class,
                    $e->getMessage()
                );
                continue;
            }
            // check to make sure the expected class exists
            if (!$r) {
                throw new InvalidArgumentException(sprintf('Expected to find class "%s" in file "%s" while importing services from resource "%s", but it was not found! Check the namespace prefix used with the resource.', $class, $path, $pattern));
            }

            if ($r->isInstantiable() || $r->isInterface()) {
                $classes[$class] = null;
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
}
