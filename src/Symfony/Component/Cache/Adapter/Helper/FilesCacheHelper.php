<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter\Helper;

use Symfony\Component\Cache\Exception\InvalidArgumentException;

class FilesCacheHelper
{
    /**
     * @var string
     */
    private $fileSuffix;

    /**
     * @var string
     */
    private $directory;

    /**
     * @param string $directory  Path where cache items should be stored, defaults to sys_get_temp_dir().'/symfony-cache'
     * @param string $namespace  Cache namespace
     * @param string $version    Version (works the same way as namespace)
     * @param string $fileSuffix Suffix that will be appended to all file names
     */
    public function __construct($directory = null, $namespace = null, $version = null, $fileSuffix = '')
    {
        if (!isset($directory[0])) {
            $directory = sys_get_temp_dir().'/symfony-cache';
        }
        if (isset($namespace[0])) {
            if (preg_match('#[^-+_.A-Za-z0-9]#', $namespace, $match)) {
                throw new InvalidArgumentException(sprintf('Cache namespace for filesystem cache contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
            }
            $directory .= '/'.$namespace;
        }
        if (isset($version[0])) {
            if (preg_match('#[^-+_.A-Za-z0-9]#', $version, $match)) {
                throw new InvalidArgumentException(sprintf('Cache version contains "%s" but only characters in [-+_.A-Za-z0-9] are allowed.', $match[0]));
            }
            $directory .= '/'.$version;
        }
        if (!file_exists($dir = $directory.'/.')) {
            @mkdir($directory, 0777, true);
        }
        if (false === $dir = realpath($dir)) {
            throw new InvalidArgumentException(sprintf('Cache directory does not exist (%s)', $directory));
        }
        if (!is_writable($dir .= DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(sprintf('Cache directory is not writable (%s)', $directory));
        }
        // On Windows the whole path is limited to 258 chars
        if ('\\' === DIRECTORY_SEPARATOR && strlen($dir) + strlen($fileSuffix) > 234) {
            throw new InvalidArgumentException(sprintf('Cache directory too long (%s)', $directory));
        }

        $this->fileSuffix = $fileSuffix;
        $this->directory = $dir;
    }

    /**
     * Returns root cache directory.
     *
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Saves entry in cache.
     *
     * @param string   $id               Id of the cache entry (used for obtaining file path to write to).
     * @param string   $fileContent      Content to write to cache file
     * @param int|null $modificationTime If this is not-null it will be passed to touch()
     *
     * @return bool
     */
    public function saveFileForId($id, $fileContent, $modificationTime = null)
    {
        $file = $this->getFilePath($id, true);

        return $this->saveFile($file, $fileContent, $modificationTime);
    }

    /**
     * Saves entry in cache.
     *
     * @param string   $file             File path to cache entry.
     * @param string   $fileContent      Content to write to cache file
     * @param int|null $modificationTime If this is not-null it will be passed to touch()
     *
     * @return bool
     */
    public function saveFile($file, $fileContent, $modificationTime = null)
    {
        $temporaryFile = $this->directory.uniqid('', true);
        if (false === @file_put_contents($temporaryFile, $fileContent)) {
            return false;
        }

        if (null !== $modificationTime) {
            @touch($temporaryFile, $modificationTime);
        }

        return @rename($temporaryFile, $file);
    }

    /**
     * Returns file path to cache entry.
     *
     * @param string $id    Cache entry id.
     * @param bool   $mkdir Whether to create necessary directories before returning file path.
     *
     * @return string
     */
    public function getFilePath($id, $mkdir = false)
    {
        $hash = str_replace('/', '-', base64_encode(md5($id, true)));
        $dir = $this->directory.$hash[0].DIRECTORY_SEPARATOR.$hash[1].DIRECTORY_SEPARATOR;

        if ($mkdir && !file_exists($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir.substr($hash, 2, -2).$this->fileSuffix;
    }
}
