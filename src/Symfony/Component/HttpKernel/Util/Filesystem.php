<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Util;

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

        $mostRecent = false;
        if (file_exists($targetFile)) {
            $statTarget = stat($targetFile);
            $statOrigin = stat($originFile);
            $mostRecent = $statOrigin['mtime'] > $statTarget['mtime'];
        }

        if ($override || !file_exists($targetFile) || $mostRecent) {
            copy($originFile, $targetFile);
        }
    }

    /**
     * Creates a directory recursively.
     *
     * @param  string|array|\Traversable $dirs The directory path
     * @param  int                       $mode The directory mode
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
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to remove
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
            if (!file_exists($file)) {
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
     * @param string|array|\Traversable $files A filename, an array of files, or a \Traversable instance to remove
     * @param integer                   $mode  The new mode
     * @param integer                   $umask The mode mask (octal)
     */
    public function chmod($files, $mode, $umask = 0000)
    {
        $currentUmask = umask();
        umask($umask);

        foreach ($this->toIterator($files) as $file) {
            chmod($file, $mode);
        }

        umask($currentUmask);
    }

    /**
     * Renames a file.
     *
     * @param string $origin  The origin filename
     * @param string $target  The new filename
     *
     * @throws \RuntimeException When target file already exists
     */
    public function rename($origin, $target)
    {
        // we check that target does not exist
        if (is_readable($target)) {
            throw new \RuntimeException(sprintf('Cannot rename because the target "%" already exist.', $target));
        }

        rename($origin, $target);
    }

    /**
     * Creates a symbolic link or copy a directory.
     *
     * @param string  $originDir     The origin directory path
     * @param string  $targetDir     The symbolic link name
     * @param Boolean $copyOnWindows Whether to copy files if on windows
     */
    public function symlink($originDir, $targetDir, $copyOnWindows = false)
    {
        if (!function_exists('symlink') && $copyOnWindows) {
            $this->mirror($originDir, $targetDir);

            return;
        }

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
     * Mirrors a directory to another.
     *
     * @param string $originDir      The origin directory
     * @param string $targetDir      The target directory
     * @param \Traversable $iterator A Traversable instance
     * @param array  $options        An array of options (see copy())
     *
     * @throws \RuntimeException When file type is unknown
     */
    public function mirror($originDir, $targetDir, \Traversable $iterator = null, $options = array())
    {
        if (null === $iterator) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($originDir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        }

        if ('/' === substr($targetDir, -1) || '\\' === substr($targetDir, -1)) {
            $targetDir = substr($targetDir, 0, -1);
        }

        if ('/' === substr($originDir, -1) || '\\' === substr($originDir, -1)) {
            $originDir = substr($originDir, 0, -1);
        }

        foreach ($iterator as $file) {
            $target = $targetDir.'/'.str_replace($originDir.DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (is_dir($file)) {
                $this->mkdir($target);
            } else if (is_file($file)) {
                $this->copy($file, $target, $options);
            } else if (is_link($file)) {
                $this->symlink($file, $target);
            } else {
                throw new \RuntimeException(sprintf('Unable to guess "%s" file type.', $file));
            }
        }
    }

    private function toIterator($files)
    {
        if (!$files instanceof \Traversable) {
            $files = new \ArrayObject(is_array($files) ? $files : array($files));
        }

        return $files;
    }
}
