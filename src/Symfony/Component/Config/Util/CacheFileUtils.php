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
     * Dumps content into a file, trying to make it atomically. The directory for the file must exist.
     * @param  string $filename  The file to be written to.
     * @param  string $content   The data to write into the file.
     * @throws \RuntimeException If the file cannot be written to.
     */
    public static function dumpInFile($filename, $content)
    {
        $dir = dirname($filename);

        if (!is_dir($dir)) {
            if (false === @mkdir($dir, 0777, true)) {
                throw new \RuntimeException(sprintf('Unable to create the %s directory\n', $dir));
            }
        } elseif (!is_writable($dir)) {
            throw new \RuntimeException(sprintf('Unable to write in the %s directory\n', $dir));
        }

        $tmpFile = tempnam($dir, basename($filename));

        if (false !== @file_put_contents($tmpFile, $content) && @rename($tmpFile, $filename)) {
            @chmod($filename, 0666 & ~umask());
        } else {
            throw new \RuntimeException(sprintf('Failed to write cache file "%s".', $filename));
        }
    }
}
