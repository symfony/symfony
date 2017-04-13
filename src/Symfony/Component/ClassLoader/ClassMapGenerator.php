<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ClassLoader;

@trigger_error('The '.__NAMESPACE__.'\ClassMapGenerator class is deprecated since version 3.3 and will be removed in 4.0. Use Composer instead.', E_USER_DEPRECATED);

/**
 * ClassMapGenerator.
 *
 * @author Gyula Sallai <salla016@gmail.com>
 *
 * @deprecated since version 3.3, to be removed in 4.0.
 */
class ClassMapGenerator
{
    /**
     * Generate a class map file.
     *
     * @param array|string $dirs Directories or a single path to search in
     * @param string       $file The name of the class map file
     */
    public static function dump($dirs, $file)
    {
        $dirs = (array) $dirs;
        $maps = array();

        foreach ($dirs as $dir) {
            $maps = array_merge($maps, static::createMap($dir));
        }

        file_put_contents($file, sprintf('<?php return %s;', var_export($maps, true)));
    }

    /**
     * Iterate over all files in the given directory searching for classes.
     *
     * @param \Iterator|string $dir The directory to search in or an iterator
     *
     * @return array A class map array
     */
    public static function createMap($dir)
    {
        if (is_string($dir)) {
            $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        }

        $map = array();

        foreach ($dir as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getRealPath() ?: $file->getPathname();

            if ('php' !== pathinfo($path, PATHINFO_EXTENSION)) {
                continue;
            }

            $classes = ClassFinder::findClasses($path);

            if (\PHP_VERSION_ID >= 70000) {
                // PHP 7 memory manager will not release after token_get_all(), see https://bugs.php.net/70098
                gc_mem_caches();
            }

            foreach ($classes as $class) {
                $map[$class] = $path;
            }
        }

        return $map;
    }
}
