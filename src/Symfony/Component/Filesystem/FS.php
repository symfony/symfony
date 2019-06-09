<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Filesystem;

use function func_get_args;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Provides basic utility to manipulate the file system.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FS
{
    private static $instance;

    public static function setInstance(Filesystem $fs): void
    {
        self::$instance = $fs;
    }

    public static function getInstance(): ?Filesystem
    {
        return self::$instance;
    }

    /**
     * Copies a file.
     *
     * If the target file is older than the origin file, it's always overwritten.
     * If the target file is newer, it is overwritten only when the
     * $overwriteNewerFiles option is set to true.
     *
     * @param string $originFile          The original filename
     * @param string $targetFile          The target filename
     * @param bool   $overwriteNewerFiles If true, target files newer than origin files are overwritten
     *
     * @throws FileNotFoundException When originFile doesn't exist
     * @throws IOException           When copy fails
     */
    public static function copy($originFile, $targetFile, $overwriteNewerFiles = false)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Creates a directory recursively.
     *
     * @param string|iterable $dirs The directory path
     * @param int             $mode The directory mode
     *
     * @throws IOException On any directory creation failure
     */
    public static function mkdir($dirs, $mode = 0777)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Checks the existence of files or directories.
     *
     * @param string|iterable $files A filename, an array of files, or a \Traversable instance to check
     *
     * @return bool true if the file exists, false otherwise
     */
    public static function exists($files)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Sets access and modification time of file.
     *
     * @param string|iterable $files A filename, an array of files, or a \Traversable instance to create
     * @param int|null        $time  The touch time as a Unix timestamp, if not supplied the current system time is used
     * @param int|null        $atime The access time as a Unix timestamp, if not supplied the current system time is used
     *
     * @throws IOException When touch fails
     */
    public static function touch($files, $time = null, $atime = null)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Removes files or directories.
     *
     * @param string|iterable $files A filename, an array of files, or a \Traversable instance to remove
     *
     * @throws IOException When removal fails
     */
    public static function remove($files)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Change mode for an array of files or directories.
     *
     * @param string|iterable $files     A filename, an array of files, or a \Traversable instance to change mode
     * @param int             $mode      The new mode (octal)
     * @param int             $umask     The mode mask (octal)
     * @param bool            $recursive Whether change the mod recursively or not
     *
     * @throws IOException When the change fails
     */
    public static function chmod($files, $mode, $umask = 0000, $recursive = false)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Change the owner of an array of files or directories.
     *
     * @param string|iterable $files     A filename, an array of files, or a \Traversable instance to change owner
     * @param string          $user      The new owner user name
     * @param bool            $recursive Whether change the owner recursively or not
     *
     * @throws IOException When the change fails
     */
    public static function chown($files, $user, $recursive = false)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Change the group of an array of files or directories.
     *
     * @param string|iterable $files     A filename, an array of files, or a \Traversable instance to change group
     * @param string          $group     The group name
     * @param bool            $recursive Whether change the group recursively or not
     *
     * @throws IOException When the change fails
     */
    public static function chgrp($files, $group, $recursive = false)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Renames a file or a directory.
     *
     * @param string $origin    The origin filename or directory
     * @param string $target    The new filename or directory
     * @param bool   $overwrite Whether to overwrite the target if it already exists
     *
     * @throws IOException When target file or directory already exists
     * @throws IOException When origin cannot be renamed
     */
    public static function rename($origin, $target, $overwrite = false)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Creates a symbolic link or copy a directory.
     *
     * @param string $originDir     The origin directory path
     * @param string $targetDir     The symbolic link name
     * @param bool   $copyOnWindows Whether to copy files if on Windows
     *
     * @throws IOException When symlink fails
     */
    public static function symlink($originDir, $targetDir, $copyOnWindows = false)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Creates a hard link, or several hard links to a file.
     *
     * @param string          $originFile  The original file
     * @param string|string[] $targetFiles The target file(s)
     *
     * @throws FileNotFoundException When original file is missing or not a file
     * @throws IOException           When link fails, including if link already exists
     */
    public static function hardlink($originFile, $targetFiles)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Resolves links in paths.
     *
     * With $canonicalize = false (default)
     *      - if $path does not exist or is not a link, returns null
     *      - if $path is a link, returns the next direct target of the link without considering the existence of the target
     *
     * With $canonicalize = true
     *      - if $path does not exist, returns null
     *      - if $path exists, returns its absolute fully resolved final version
     *
     * @param string $path         A filesystem path
     * @param bool   $canonicalize Whether or not to return a canonicalized path
     *
     * @return string|null
     */
    public static function readlink($path, $canonicalize = false)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Given an existing path, convert it to a path relative to a given starting path.
     *
     * @param string $endPath   Absolute path of target
     * @param string $startPath Absolute path where traversal begins
     *
     * @return string Path of target relative to starting path
     */
    public static function makePathRelative($endPath, $startPath)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Mirrors a directory to another.
     *
     * Copies files and directories from the origin directory into the target directory. By default:
     *
     *  - existing files in the target directory will be overwritten, except if they are newer (see the `override` option)
     *  - files in the target directory that do not exist in the source directory will not be deleted (see the `delete` option)
     *
     * @param string            $originDir The origin directory
     * @param string            $targetDir The target directory
     * @param \Traversable|null $iterator  Iterator that filters which files and directories to copy, if null a recursive iterator is created
     * @param array             $options   An array of boolean options
     *                                     Valid options are:
     *                                     - $options['override'] If true, target files newer than origin files are overwritten (see copy(), defaults to false)
     *                                     - $options['copy_on_windows'] Whether to copy files instead of links on Windows (see symlink(), defaults to false)
     *                                     - $options['delete'] Whether to delete files that are not in the source directory (defaults to false)
     *
     * @throws IOException When file type is unknown
     */
    public static function mirror($originDir, $targetDir, \Traversable $iterator = null, $options = [])
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @param string $file A file path
     *
     * @return bool
     */
    public static function isAbsolutePath($file)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Creates a temporary file with support for custom stream wrappers.
     *
     * @param string $dir    The directory where the temporary filename will be created
     * @param string $prefix The prefix of the generated temporary filename
     *                       Note: Windows uses only the first three characters of prefix
     *
     * @return string The new temporary filename (with path), or throw an exception on failure
     */
    public static function tempnam($dir, $prefix)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Atomically dumps content into a file.
     *
     * @param string          $filename The file to be written to
     * @param string|resource $content  The data to write into the file
     *
     * @throws IOException if the file cannot be written to
     */
    public static function dumpFile($filename, $content)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * Appends content to an existing file.
     *
     * @param string          $filename The file to which to append content
     * @param string|resource $content  The content to append
     *
     * @throws IOException If the file is not writable
     */
    public static function appendToFile($filename, $content)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    /**
     * @internal
     */
    public static function handleError($type, $msg)
    {
        return self::__callStatic(__FUNCTION__, func_get_args());
    }

    public static function __callStatic($method, $args) {
        if (null === self::$instance) {
            self::$instance = new Filesystem();
        }

        return call_user_func_array([self::$instance, $method], $args);
    }

    private function __construct()
    {
    }
}
