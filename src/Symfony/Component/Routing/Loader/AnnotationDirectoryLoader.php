<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Config\Resource\DirectoryResource;

/**
 * AnnotationDirectoryLoader loads routing information from annotations set
 * on PHP classes and methods.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AnnotationDirectoryLoader extends AnnotationFileLoader
{
    /**
     * Loads from annotations from a directory.
     *
     * @param string      $path A directory path
     * @param string|null $type The resource type
     *
     * @return RouteCollection A RouteCollection instance
     *
     * @throws \InvalidArgumentException When the directory does not exist or its routes cannot be parsed
     */
    public function load($path, $type = null)
    {
        $dir = $this->locator->locate($path);

        $collection = new RouteCollection();
        $collection->addResource(new DirectoryResource($dir, '/\.php$/'));
        $files = iterator_to_array(new \RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir),
                function (\SplFileInfo $current) {
                    return '.' !== substr($current->getBasename(), 0, 1);
                }
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY
        ));
        usort($files, function (\SplFileInfo $a, \SplFileInfo $b) {
            return (string) $a > (string) $b ? 1 : -1;
        });

        foreach ($files as $file) {
            if (!$file->isFile() || '.php' !== substr($file->getFilename(), -4)) {
                continue;
            }

            if ($class = $this->findClass($file)) {
                $refl = new \ReflectionClass($class);
                if ($refl->isAbstract()) {
                    continue;
                }

                $collection->addCollection($this->loader->load($class, $type));
            }
        }

        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        if (!is_string($resource)) {
            return false;
        }

        try {
            $path = $this->locator->locate($resource);
        } catch (\Exception $e) {
            return false;
        }

        return is_dir($path) && (!$type || 'annotation' === $type);
    }
}

/**
 * @internal To be removed as RecursiveCallbackFilterIterator is available since PHP 5.4
 */
class RecursiveCallbackFilterIterator extends \FilterIterator implements \RecursiveIterator
{
    private $iterator;
    private $callback;

    public function __construct(\RecursiveIterator $iterator, $callback)
    {
        $this->iterator = $iterator;
        $this->callback = $callback;
        parent::__construct($iterator);
    }

    public function accept()
    {
        return call_user_func($this->callback, $this->current(), $this->key(), $this->iterator);
    }

    public function hasChildren()
    {
        return $this->iterator->hasChildren();
    }

    public function getChildren()
    {
        return new static($this->iterator->getChildren(), $this->callback);
    }
}
