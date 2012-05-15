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

/**
 * Provides basic utility to manipulate the file system.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Filesystem
{
    /**
     * Copies a file.
     *
     * This method only copies the file if the origin file is newer than the target file.
     *
     * By default, if the target already exists, it is not overridden.
     *
     * @param string $originFile The original filename
     * @param string $targetFile The target filename
     * @param array  $override   Whether to override an existing file or not
     */
    public function copy($originFile, $targetFile, $override = false)
    {
        $this->mkdir(dirname($targetFile));

        if (!$override && is_file($targetFile)) {
            $doCopy = filemtime($originFile) > filemtime($targetFile);
        } else {
            $doCopy = true;
        }

        if ($doCopy) {
            copy($originFile, $targetFile);
        }
    }

    /**
     * Creates a directory recursively.
     *
     * @param string|array|\Traversable $dirs The directory path
     * @param int                       $mode The directory mode
     *
     * @return Boolean true if the directory has been created, false otherwise
     */
    public function mkdir($dirs, $mode = 0777)
    {
        $ret = true;
        foreach ($this->toIterator($dirs) as $dir) {
            if (is_dir($dir)) {
                continue;
            }

            $ret = @mkdir($dir, $mode, true) && $ret;
        }

        return $ret;
    }

    /**
     * Creates empty files.
     *
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to create
     */
    public function touch($files)
    {
        foreach ($this->toIterator($files) as $file) {
            touch($file);
        }
    }

    /**
     * Removes files or directories.
     *
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to remove
     */
    public function remove($files)
    {
        $files = iterator_to_array($this->toIterator($files));
        $files = array_reverse($files);
        foreach ($files as $file) {
            if (!file_exists($file) && !is_link($file)) {
                continue;
            }

            if (is_dir($file) && !is_link($file)) {
                $this->remove(new \FilesystemIterator($file));

                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }

    /**
     * Change mode for an array of files or directories.
     *
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to change mode
     * @param integer                   $mode  The new mode (octal)
     * @param integer                   $umask The mode mask (octal)
     */
    public function chmod($files, $mode, $umask = 0000)
    {
        foreach ($this->toIterator($files) as $file) {
            @chmod($file, $mode & ~$umask);
        }
    }

    /**
     * Renames a file.
     *
     * @param string $origin The origin filename
     * @param string $target The new filename
     *
     * @throws \RuntimeException When target file already exists
     * @throws \RuntimeException When origin cannot be renamed
     */
    public function rename($origin, $target)
    {
        // we check that target does not exist
        if (is_readable($target)) {
            throw new \RuntimeException(sprintf('Cannot rename because the target "%s" already exist.', $target));
        }

        if (false === @rename($origin, $target)) {
            throw new \RuntimeException(sprintf('Cannot rename "%s" to "%s".', $origin, $target));
        }
    }

    /**
     * Creates a symbolic link or copy a directory.
     *
     * @param string  $originDir     The origin directory path
     * @param string  $targetDir     The symbolic link name
     * @param Boolean $copyOnWindows Whether to copy files if on Windows
     */
    public function symlink($originDir, $targetDir, $copyOnWindows = false)
    {
        if (!function_exists('symlink') && $copyOnWindows) {
            $this->mirror($originDir, $targetDir);

            return;
        }

        $this->mkdir(dirname($targetDir));

        $ok = false;
        if (is_link($targetDir)) {
            if (readlink($targetDir) != $originDir) {
                unlink($targetDir);
            } else {
                $ok = true;
            }
        }

        if (!$ok) {
            symlink($originDir, $targetDir);
        }
    }

    /**
     * Given an existing path, convert it to a path relative to a given starting path
     *
     * @param string $endPath   Absolute path of target
     * @param string $startPath Absolute path where traversal begins
     *
     * @return string Path of target relative to starting path
     */
    public function makePathRelative($endPath, $startPath)
    {
        // Normalize separators on windows
        if (defined('PHP_WINDOWS_VERSION_MAJOR')) {
            $endPath = strtr($endPath, '\\', '/');
            $startPath = strtr($startPath, '\\', '/');
        }

        // Find for which character the the common path stops
        $offset = 0;
        while (isset($startPath[$offset]) && isset($endPath[$offset]) && $startPath[$offset] === $endPath[$offset]) {
            $offset++;
        }

        // Determine how deep the start path is relative to the common path (ie, "web/bundles" = 2 levels)
        $diffPath = trim(substr($startPath, $offset), '/');
        $depth = strlen($diffPath) > 0 ? substr_count($diffPath, '/') + 1 : 0;

        // Repeated "../" for each level need to reach the common path
        $traverser = str_repeat('../', $depth);

        // Construct $endPath from traversing to the common path, then to the remaining $endPath
        return $traverser.substr($endPath, $offset);
    }

    /**
     * Mirrors a directory to another.
     *
     * @param string       $originDir The origin directory
     * @param string       $targetDir The target directory
     * @param \Traversable $iterator  A Traversable instance
     * @param array        $options   An array of boolean options
     *                               Valid options are:
     *                                 - $options['override'] Whether to override an existing file on copy or not (see copy())
     *                                 - $options['copy_on_windows'] Whether to copy files instead of links on Windows (see symlink())
     *
     * @throws \RuntimeException When file type is unknown
     */
    public function mirror($originDir, $targetDir, \Traversable $iterator = null, $options = array())
    {
        $copyOnWindows = false;
        if (isset($options['copy_on_windows']) && !function_exists('symlink')) {
            $copyOnWindows = $options['copy_on_windows'];
        }

        if (null === $iterator) {
            $flags = $copyOnWindows ? \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS : \FilesystemIterator::SKIP_DOTS;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($originDir, $flags), \RecursiveIteratorIterator::SELF_FIRST);
        }

        if ('/' === substr($targetDir, -1) || '\\' === substr($targetDir, -1)) {
            $targetDir = substr($targetDir, 0, -1);
        }

        if ('/' === substr($originDir, -1) || '\\' === substr($originDir, -1)) {
            $originDir = substr($originDir, 0, -1);
        }

        foreach ($iterator as $file) {
            $target = str_replace($originDir, $targetDir, $file->getPathname());

            if (is_dir($file)) {
                $this->mkdir($target);
            } elseif (!$copyOnWindows && is_link($file)) {
                $this->symlink($file, $target);
            } elseif (is_file($file) || ($copyOnWindows && is_link($file))) {
                $this->copy($file, $target, isset($options['override']) ? $options['override'] : false);
            } else {
                throw new \RuntimeException(sprintf('Unable to guess "%s" file type.', $file));
            }
        }
    }

    /**
     * Returns whether the file path is an absolute path.
     *
     * @param string $file A file path
     *
     * @return Boolean
     */
    public function isAbsolutePath($file)
    {
        if ($file[0] == '/' || $file[0] == '\\'
            || (strlen($file) > 3 && ctype_alpha($file[0])
                && $file[1] == ':'
                && ($file[2] == '\\' || $file[2] == '/')
            )
            || null !== parse_url($file, PHP_URL_SCHEME)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $files
     *
     * @return \Traversable
     */
    private function toIterator($files)
    {
        if (!$files instanceof \Traversable) {
            $files = new \ArrayObject(is_array($files) ? $files : array($files));
        }

        return $files;
    }
}
