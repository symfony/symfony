<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Util;

/**
 * CacheFileUtils contains utility method to atomically (?) write files.
 *
 * This class contains static methods only and is not meant to be instantiated.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class CacheFileUtils
{

    /**
     * Make sure the given directory can be used as a cache directory, creating it if necessary.
     * @param  string $dir       The directory path
     * @param  string $name      An optional name to be used in the exception message in case of failure
     * @throws \RuntimeException if the directory cannot be created or written to.
     */
    public static function createCacheDir($dir, $name = null)
    {
        if ($name) {
            $name = " ($name)";
        }

        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0777, true)) {
                throw new \RuntimeException(sprintf('Unable to create the %s directory%s\n', $dir, $name));
            }
        } elseif (!is_writable($dir)) {
            throw new \RuntimeException(sprintf('Unable to write in the %s directory%s\n', $dir, $name));
        }
    }

    /**
     * Tries to create a writeable directory that can contain a given file.
     *
     * @param  string $filename  The file the containing directory has to be created for
     * @return string            The directory now available for caching
     * @throws \RuntimeException If the directory cannot be created or written to
     */
    public static function createDirectoryForFile($filename)
    {
        $dir = dirname($filename);
        self::createCacheDir($dir);
        return $dir;
    }

    /**
     * Dumps content into a file, trying to make it atomically. The directory for the file must exist.
     * @param  string $filename  The file to be written to.
     * @param  string $content   The data to write into the file.
     * @throws \RuntimeException If the file cannot be written to.
     */
    public static function dumpInFile($filename, $content)
    {
        $dir = self::createDirectoryForFile($filename);

        $tmpFile = tempnam($dir, basename($filename));
        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $filename)) {
            @chmod($filename, 0666 & ~umask());
        } else {
            throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $filename));
        }

    }
}
