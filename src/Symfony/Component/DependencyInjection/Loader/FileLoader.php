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

use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Config\Loader\FileLoader as BaseFileLoader;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\Glob;

/**
 * FileLoader is the abstract class used by all built-in loaders that are file based.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class FileLoader extends BaseFileLoader
{
    protected $container;

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
     * {@inheritdoc}
     */
    public function import($resource, $type = null, $ignoreErrors = false, $sourceResource = null)
    {
        try {
            foreach ($this->glob($resource, false) as $path => $info) {
                parent::import($path, $type, $ignoreErrors, $sourceResource);
            }
        } catch (FileLocatorFileNotFoundException $e) {
            if (!$ignoreErrors) {
                throw $e;
            }
        }
    }

    /**
     * Registers a set of classes as services using PSR-4 for discovery.
     *
     * @param Definition $prototype A definition to use as template
     * @param string     $namespace The namespace prefix of classes in the scanned directory
     * @param string     $resource  The directory to look for classes, glob-patterns allowed
     *
     * @experimental in version 3.3
     */
    public function registerClasses(Definition $prototype, $namespace, $resource)
    {
        if ('\\' !== substr($namespace, -1)) {
            throw new InvalidArgumentException(sprintf('Namespace prefix must end with a "\\": %s.', $namespace));
        }
        if (!preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+\\\\)++$/', $namespace)) {
            throw new InvalidArgumentException(sprintf('Namespace is not a valid PSR-4 prefix: %s.', $namespace));
        }

        $classes = $this->findClasses($namespace, $resource);
        // prepare for deep cloning
        $prototype = serialize($prototype);

        foreach ($classes as $class) {
            $this->container->setDefinition($class, unserialize($prototype));
        }
    }

    private function findClasses($namespace, $resource)
    {
        $classes = array();
        $extRegexp = defined('HHVM_VERSION') ? '/\\.(?:php|hh)$/' : '/\\.php$/';

        foreach ($this->glob($resource, true, $prefixLen) as $path => $info) {
            if (!preg_match($extRegexp, $path, $m) || !$info->isReadable()) {
                continue;
            }
            $class = $namespace.ltrim(str_replace('/', '\\', substr($path, $prefixLen, -strlen($m[0]))), '\\');

            if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+$/', $class)) {
                continue;
            }
            if (!$r = $this->container->getReflectionClass($class, true)) {
                continue;
            }
            if (!$r->isInterface() && !$r->isTrait()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function glob($resource, $recursive, &$prefixLen = null)
    {
        if (strlen($resource) === $i = strcspn($resource, '*?{[')) {
            if (!$recursive) {
                yield $resource => new \SplFileInfo($resource);

                return;
            }
            $resourcePrefix = $resource;
            $resource = '';
        } elseif (0 === $i) {
            $resourcePrefix = '.';
            $resource = '/'.$resource;
        } else {
            $resourcePrefix = dirname(substr($resource, 0, 1 + $i));
            $resource = substr($resource, strlen($resourcePrefix));
        }

        $resourcePrefix = $this->locator->locate($resourcePrefix, $this->currentDir, true);
        $resourcePrefix = realpath($resourcePrefix) ?: $resourcePrefix;
        $prefixLen = strlen($resourcePrefix);

        // track directories only for new & removed files
        $this->container->fileExists($resourcePrefix, '/^$/');

        if (false === strpos($resource, '/**/') && (defined('GLOB_BRACE') || false === strpos($resource, '{'))) {
            foreach (glob($resourcePrefix.$resource, defined('GLOB_BRACE') ? GLOB_BRACE : 0) as $path) {
                if ($recursive && is_dir($path)) {
                    $flags = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS;
                    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, $flags)) as $path => $info) {
                        if ($info->isFile()) {
                            yield $path => $info;
                        }
                    }
                } elseif (is_file($path)) {
                    yield $path => new \SplFileInfo($path);
                }
            }

            return;
        }

        if (!class_exists(Finder::class)) {
            throw new LogicException(sprintf('Extended glob pattern "%s" cannot be used as the Finder component is not installed.', $resource));
        }

        $finder = new Finder();
        $regex = Glob::toRegex($resource);
        if ($recursive) {
            $regex = substr_replace($regex, '(/|$)', -2, 1);
        }

        foreach ($finder->followLinks()->in($resourcePrefix) as $path => $info) {
            if (preg_match($regex, substr($path, $prefixLen)) && $info->isFile()) {
                yield $path => $info;
            }
        }
    }
}
