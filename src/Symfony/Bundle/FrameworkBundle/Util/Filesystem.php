<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Util;

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
     * To override existing files, pass the "override" option.
     *
     * @param string $originFile  The original filename
     * @param string $targetFile  The target filename
     * @param array  $options     An array of options
     */
    public function copy($originFile, $targetFile, $options = array())
    {
        if (!array_key_exists('override', $options)) {
            $options['override'] = false;
        }

        $this->mkdirs(dirname($targetFile));

        $mostRecent = false;
        if (file_exists($targetFile)) {
            $statTarget = stat($targetFile);
            $statOrigin = stat($originFile);
            $mostRecent = $statOrigin['mtime'] > $statTarget['mtime'];
        }

        if ($options['override'] || !file_exists($targetFile) || $mostRecent) {
            copy($originFile, $targetFile);
        }
    }

    /**
     * Creates a directory recursively.
     *
     * @param  string $path  The directory path
     * @param  int    $mode  The directory mode
     *
     * @return Boolean true if the directory has been created, false otherwise
     */
    public function mkdirs($path, $mode = 0777)
    {
        if (is_dir($path)) {
            return true;
        }

        return @mkdir($path, $mode, true);
    }

    /**
     * Creates empty files.
     *
     * @param mixed $files  The filename, or an array of filenames
     */
    public function touch($files)
    {
        if (!is_array($files)) {
            $files = array($files);
        }

        foreach ($files as $file) {
            touch($file);
        }
    }

    /**
     * Removes files or directories.
     *
     * @param mixed $files  A filename or an array of files to remove
     */
    public function remove($files)
    {
        if (!is_array($files)) {
            $files = array($files);
        }

        $files = array_reverse($files);
        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }

            if (is_dir($file) && !is_link($file)) {
                $fp = opendir($file);
                while (false !== $item = readdir($fp)) {
                    if (!in_array($item, array('.', '..'))) {
                        $this->remove($file.'/'.$item);
                    }
                }
                closedir($fp);

                rmdir($file);
            } else {
                unlink($file);
            }
        }
    }

    /**
     * Change mode for an array of files or directories.
     *
     * @param array   $files  An array of files or directories
     * @param integer $mode   The new mode
     * @param integer $umask  The mode mask (octal)
     */
    public function chmod($files, $mode, $umask = 0000)
    {
        $currentUmask = umask();
        umask($umask);

        if (!is_array($files)) {
            $files = array($files);
        }

        foreach ($files as $file) {
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
     * @param string  $originDir      The origin directory path
     * @param string  $targetDir      The symbolic link name
     * @param Boolean $copyOnWindows  Whether to copy files if on windows
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
                $this->mkdirs($target);
            } else if (is_file($file)) {
                $this->copy($file, $target, $options);
            } else if (is_link($file)) {
                $this->symlink($file, $target);
            } else {
                throw new \RuntimeException(sprintf('Unable to guess "%s" file type.', $file));
            }
        }
    }
}
