<?php

namespace Symfony\Component\HttpKernel;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ClassCollectionLoader.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ClassCollectionLoader
{
    static protected $loaded;

    /**
     * Loads a list of classes and caches them in one big file.
     *
     * @param array   $classes    An array of classes to load
     * @param string  $cacheDir   A cache directory
     * @param string  $name       The cache name prefix
     * @param Boolean $autoReload Whether to flush the cache when the cache is stale or not
     * @param Boolean $adaptive   Whether to remove already declared classes or not
     *
     * @throws \InvalidArgumentException When class can't be loaded
     */
    static public function load($classes, $cacheDir, $name, $autoReload, $adaptive = false)
    {
        // each $name can only be loaded once per PHP process
        if (isset(self::$loaded[$name])) {
            return;
        }

        self::$loaded[$name] = true;

        $classes = array_unique($classes);

        if ($adaptive) {
            // don't include already declared classes
            $classes = array_diff($classes, get_declared_classes(), get_declared_interfaces());

            // the cache is different depending on which classes are already declared
            $name = $name.'-'.substr(md5(implode('|', $classes)), 0, 5);
        }

        $cache = $cacheDir.'/'.$name.'.php';

        // auto-reload
        $reload = false;
        if ($autoReload) {
            $metadata = $cacheDir.'/'.$name.'.meta';
            if (!file_exists($metadata) || !file_exists($cache)) {
                $reload = true;
            } else {
                $time = filemtime($cache);
                $meta = unserialize(file_get_contents($metadata));

                if ($meta[1] != $classes) {
                    $reload = true;
                } else {
                    foreach ($meta[0] as $resource) {
                        if (!file_exists($resource) || filemtime($resource) > $time) {
                            $reload = true;

                            break;
                        }
                    }
                }
            }
        }

        if (!$reload && file_exists($cache)) {
            require_once $cache;

            return;
        }

        $files = array();
        $content = '';
        foreach ($classes as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                throw new \InvalidArgumentException(sprintf('Unable to load class "%s"', $class));
            }

            $r = new \ReflectionClass($class);
            $files[] = $r->getFileName();

            $content .= preg_replace(array('/^\s*<\?php/', '/\?>\s*$/'), '', file_get_contents($r->getFileName()));
        }

        // cache the core classes
        if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0777, true);
        }
        self::writeCacheFile($cache, Kernel::stripComments('<?php '.$content));

        if ($autoReload) {
            // save the resources
            self::writeCacheFile($metadata, serialize(array($files, $classes)));
        }
    }

    static protected function writeCacheFile($file, $content)
    {
        $tmpFile = tempnam(dirname($file), basename($file));
        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $file)) {
            chmod($file, 0644);

            return;
        }

        throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $file));
    }
}
