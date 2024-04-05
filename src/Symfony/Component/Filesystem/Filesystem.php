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

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Provides basic utility to manipulate the file system.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Filesystem
{
    private static ?string $lastError = null;

    /**
     * Copies a file.
     *
     * If the target file is older than the origin file, it's always overwritten.
     * If the target file is newer, it is overwritten only when the
     * $overwriteNewerFiles option is set to true.
     *
     * @return void
     *
     * @throws FileNotFoundException When originFile doesn't exist
     * @throws IOException           When copy fails
     */
    public function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false)
    {
        $originIsLocal = stream_is_local($originFile) || 0 === stripos($originFile, 'file://');
        if ($originIsLocal && !is_file($originFile)) {
            throw new FileNotFoundException(sprintf('Failed to copy "%s" because file does not exist.', $originFile), 0, null, $originFile);
        }

        $this->mkdir(\dirname($targetFile));

        $doCopy = true;
        if (!$overwriteNewerFiles && null === parse_url($originFile, \PHP_URL_HOST) && is_file($targetFile)) {
            $doCopy = filemtime($originFile) > filemtime($targetFile);
        }

        if ($doCopy) {
            // https://bugs.php.net/64634
            if (!$source = self::box('fopen', $originFile, 'r')) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s" because source file could not be opened for reading: ', $originFile, $targetFile).self::$lastError, 0, null, $originFile);
            }

            // Stream context created to allow files overwrite when using FTP stream wrapper - disabled by default
            if (!$target = self::box('fopen', $targetFile, 'w', false, stream_context_create(['ftp' => ['overwrite' => true]]))) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s" because target file could not be opened for writing: ', $originFile, $targetFile).self::$lastError, 0, null, $originFile);
            }

            $bytesCopied = stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);
            unset($source, $target);

            if (!is_file($targetFile)) {
                throw new IOException(sprintf('Failed to copy "%s" to "%s".', $originFile, $targetFile), 0, null, $originFile);
            }

            if ($originIsLocal) {
                // Like `cp`, preserve executable permission bits
                self::box('chmod', $targetFile, fileperms($targetFile) | (fileperms($originFile) & 0111));

                // Like `cp`, preserve the file modification time
                self::box('touch', $targetFile, filemtime($originFile));

                if ($bytesCopied !== $bytesOrigin = filesize($originFile)) {
                    throw new IOException(sprintf('Failed to copy the whole content of "%s" to "%s" (%g of %g bytes copied).', $originFile, $targetFile, $bytesCopied, $bytesOrigin), 0, null, $originFile);
                }
            }
        }
    }

    /**
     * Creates a directory recursively.
     *
     * @return void
     *
     * @throws IOException On any directory creation failure
     */
    public function mkdir(string|iterable $dirs, int $mode = 0777)
    {
        foreach ($this->toIterable($dirs) as $dir) {
            if (is_dir($dir)) {
                continue;
            }

            if (!self::box('mkdir', $dir, $mode, true) && !is_dir($dir)) {
                throw new IOException(sprintf('Failed to create "%s": ', $dir).self::$lastError, 0, null, $dir);
            }
        }
    }

    /**
     * Checks the existence of files or directories.
     */
    public function exists(string|iterable $files): bool
    {
        $maxPathLength = \PHP_MAXPATHLEN - 2;

        foreach ($this->toIterable($files) as $file) {
            if (\strlen($file) > $maxPathLength) {
                throw new IOException(sprintf('Could not check if file exist because path length exceeds %d characters.', $maxPathLength), 0, null, $file);
            }

            if (!file_exists($file)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sets access and modification time of file.
     *
     * @param int|null $time  The touch time as a Unix timestamp, if not supplied the current system time is used
     * @param int|null $atime The access time as a Unix timestamp, if not supplied the current system time is used
     *
     * @return void
     *
     * @throws IOException When touch fails
     */
    public function touch(string|iterable $files, ?int $time = null, ?int $atime = null)
    {
        foreach ($this->toIterable($files) as $file) {
            if (!($time ? self::box('touch', $file, $time, $atime) : self::box('touch', $file))) {
                throw new IOException(sprintf('Failed to touch "%s": ', $file).self::$lastError, 0, null, $file);
            }
        }
    }

    /**
     * Removes files or directories.
     *
     * @return void
     *
     * @throws IOException When removal fails
     */
    public function remove(string|iterable $files)
    {
        if ($files instanceof \Traversable) {
            $files = iterator_to_array($files, false);
        } elseif (!\is_array($files)) {
            $files = [$files];
        }

        self::doRemove($files, false);
    }

    private static function doRemove(array $files, bool $isRecursive): void
    {
        $files = array_reverse($files);
        foreach ($files as $file) {
            if (is_link($file)) {
                // See https://bugs.php.net/52176
                if (!(self::box('unlink', $file) || '\\' !== \DIRECTORY_SEPARATOR || self::box('rmdir', $file)) && file_exists($file)) {
                    throw new IOException(sprintf('Failed to remove symlink "%s": ', $file).self::$lastError);
                }
            } elseif (is_dir($file)) {
                if (!$isRecursive) {
                    $tmpName = \dirname(realpath($file)).'/.'.strrev(strtr(base64_encode(random_bytes(2)), '/=', '-_'));

                    if (file_exists($tmpName)) {
                        try {
                            self::doRemove([$tmpName], true);
                        } catch (IOException) {
                        }
                    }

                    if (!file_exists($tmpName) && self::box('rename', $file, $tmpName)) {
                        $origFile = $file;
                        $file = $tmpName;
                    } else {
                        $origFile = null;
                    }
                }

                $filesystemIterator = new \FilesystemIterator($file, \FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS);
                self::doRemove(iterator_to_array($filesystemIterator, true), true);

                if (!self::box('rmdir', $file) && file_exists($file) && !$isRecursive) {
                    $lastError = self::$lastError;

                    if (null !== $origFile && self::box('rename', $file, $origFile)) {
                        $file = $origFile;
                    }

                    throw new IOException(sprintf('Failed to remove directory "%s": ', $file).$lastError);
                }
            } elseif (!self::box('unlink', $file) && ((self::$lastError && str_contains(self::$lastError, 'Permission denied')) || file_exists($file))) {
                throw new IOException(sprintf('Failed to remove file "%s": ', $file).self::$lastError);
            }
        }
    }

    /**
     * Change mode for an array of files or directories.
     *
     * @param int  $mode      The new mode (octal)
     * @param int  $umask     The mode mask (octal)
     * @param bool $recursive Whether change the mod recursively or not
     *
     * @return void
     *
     * @throws IOException When the change fails
     */
    public function chmod(string|iterable $files, int $mode, int $umask = 0000, bool $recursive = false)
    {
        foreach ($this->toIterable($files) as $file) {
            if (!self::box('chmod', $file, $mode & ~$umask)) {
                throw new IOException(sprintf('Failed to chmod file "%s": ', $file).self::$lastError, 0, null, $file);
            }
            if ($recursive && is_dir($file) && !is_link($file)) {
                $this->chmod(new \FilesystemIterator($file), $mode, $umask, true);
            }
        }
    }

    /**
     * Change the owner of an array of files or directories.
     *
     * @param string|int $user      A user name or number
     * @param bool       $recursive Whether change the owner recursively or not
     *
     * @return void
     *
     * @throws IOException When the change fails
     */
    public function chown(string|iterable $files, string|int $user, bool $recursive = false)
    {
        foreach ($this->toIterable($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {
                $this->chown(new \FilesystemIterator($file), $user, true);
            }
            if (is_link($file) && \function_exists('lchown')) {
                if (!self::box('lchown', $file, $user)) {
                    throw new IOException(sprintf('Failed to chown file "%s": ', $file).self::$lastError, 0, null, $file);
                }
            } else {
                if (!self::box('chown', $file, $user)) {
                    throw new IOException(sprintf('Failed to chown file "%s": ', $file).self::$lastError, 0, null, $file);
                }
            }
        }
    }

    /**
     * Change the group of an array of files or directories.
     *
     * @param string|int $group     A group name or number
     * @param bool       $recursive Whether change the group recursively or not
     *
     * @return void
     *
     * @throws IOException When the change fails
     */
    public function chgrp(string|iterable $files, string|int $group, bool $recursive = false)
    {
        foreach ($this->toIterable($files) as $file) {
            if ($recursive && is_dir($file) && !is_link($file)) {
                $this->chgrp(new \FilesystemIterator($file), $group, true);
            }
            if (is_link($file) && \function_exists('lchgrp')) {
                if (!self::box('lchgrp', $file, $group)) {
                    throw new IOException(sprintf('Failed to chgrp file "%s": ', $file).self::$lastError, 0, null, $file);
                }
            } else {
                if (!self::box('chgrp', $file, $group)) {
                    throw new IOException(sprintf('Failed to chgrp file "%s": ', $file).self::$lastError, 0, null, $file);
                }
            }
        }
    }

    /**
     * Renames a file or a directory.
     *
     * @return void
     *
     * @throws IOException When target file or directory already exists
     * @throws IOException When origin cannot be renamed
     */
    public function rename(string $origin, string $target, bool $overwrite = false)
    {
        // we check that target does not exist
        if (!$overwrite && $this->isReadable($target)) {
            throw new IOException(sprintf('Cannot rename because the target "%s" already exists.', $target), 0, null, $target);
        }

        if (!self::box('rename', $origin, $target)) {
            if (is_dir($origin)) {
                // See https://bugs.php.net/54097 & https://php.net/rename#113943
                $this->mirror($origin, $target, null, ['override' => $overwrite, 'delete' => $overwrite]);
                $this->remove($origin);

                return;
            }
            throw new IOException(sprintf('Cannot rename "%s" to "%s": ', $origin, $target).self::$lastError, 0, null, $target);
        }
    }

    /**
     * Tells whether a file exists and is readable.
     *
     * @throws IOException When windows path is longer than 258 characters
     */
    private function isReadable(string $filename): bool
    {
        $maxPathLength = \PHP_MAXPATHLEN - 2;

        if (\strlen($filename) > $maxPathLength) {
            throw new IOException(sprintf('Could not check if file is readable because path length exceeds %d characters.', $maxPathLength), 0, null, $filename);
        }

        return is_readable($filename);
    }

    /**
     * Creates a symbolic link or copy a directory.
     *
     * @return void
     *
     * @throws IOException When symlink fails
     */
    public function symlink(string $originDir, string $targetDir, bool $copyOnWindows = false)
    {
        self::assertFunctionExists('symlink');

        if ('\\' === \DIRECTORY_SEPARATOR) {
            $originDir = strtr($originDir, '/', '\\');
            $targetDir = strtr($targetDir, '/', '\\');

            if ($copyOnWindows) {
                $this->mirror($originDir, $targetDir);

                return;
            }
        }

        $this->mkdir(\dirname($targetDir));

        if (is_link($targetDir)) {
            if (readlink($targetDir) === $originDir) {
                return;
            }
            $this->remove($targetDir);
        }

        if (!self::box('symlink', $originDir, $targetDir)) {
            $this->linkException($originDir, $targetDir, 'symbolic');
        }
    }

    /**
     * Creates a hard link, or several hard links to a file.
     *
     * @param string|string[] $targetFiles The target file(s)
     *
     * @return void
     *
     * @throws FileNotFoundException When original file is missing or not a file
     * @throws IOException           When link fails, including if link already exists
     */
    public function hardlink(string $originFile, string|iterable $targetFiles)
    {
        self::assertFunctionExists('link');

        if (!$this->exists($originFile)) {
            throw new FileNotFoundException(null, 0, null, $originFile);
        }

        if (!is_file($originFile)) {
            throw new FileNotFoundException(sprintf('Origin file "%s" is not a file.', $originFile));
        }

        foreach ($this->toIterable($targetFiles) as $targetFile) {
            if (is_file($targetFile)) {
                if (fileinode($originFile) === fileinode($targetFile)) {
                    continue;
                }
                $this->remove($targetFile);
            }

            if (!self::box('link', $originFile, $targetFile)) {
                $this->linkException($originFile, $targetFile, 'hard');
            }
        }
    }

    /**
     * @param string $linkType Name of the link type, typically 'symbolic' or 'hard'
     */
    private function linkException(string $origin, string $target, string $linkType): never
    {
        if (self::$lastError) {
            if ('\\' === \DIRECTORY_SEPARATOR && str_contains(self::$lastError, 'error code(1314)')) {
                throw new IOException(sprintf('Unable to create "%s" link due to error code 1314: \'A required privilege is not held by the client\'. Do you have the required Administrator-rights?', $linkType), 0, null, $target);
            }
        }
        throw new IOException(sprintf('Failed to create "%s" link from "%s" to "%s": ', $linkType, $origin, $target).self::$lastError, 0, null, $target);
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
     */
    public function readlink(string $path, bool $canonicalize = false): ?string
    {
        if (!$canonicalize && !is_link($path)) {
            return null;
        }

        if ($canonicalize) {
            if (!$this->exists($path)) {
                return null;
            }

            return realpath($path);
        }

        return readlink($path);
    }

    /**
     * Given an existing path, convert it to a path relative to a given starting path.
     */
    public function makePathRelative(string $endPath, string $startPath): string
    {
        if (!$this->isAbsolutePath($startPath)) {
            throw new InvalidArgumentException(sprintf('The start path "%s" is not absolute.', $startPath));
        }

        if (!$this->isAbsolutePath($endPath)) {
            throw new InvalidArgumentException(sprintf('The end path "%s" is not absolute.', $endPath));
        }

        // Normalize separators on Windows
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $endPath = str_replace('\\', '/', $endPath);
            $startPath = str_replace('\\', '/', $startPath);
        }

        $splitDriveLetter = fn ($path) => (\strlen($path) > 2 && ':' === $path[1] && '/' === $path[2] && ctype_alpha($path[0]))
            ? [substr($path, 2), strtoupper($path[0])]
            : [$path, null];

        $splitPath = function ($path) {
            $result = [];

            foreach (explode('/', trim($path, '/')) as $segment) {
                if ('..' === $segment) {
                    array_pop($result);
                } elseif ('.' !== $segment && '' !== $segment) {
                    $result[] = $segment;
                }
            }

            return $result;
        };

        [$endPath, $endDriveLetter] = $splitDriveLetter($endPath);
        [$startPath, $startDriveLetter] = $splitDriveLetter($startPath);

        $startPathArr = $splitPath($startPath);
        $endPathArr = $splitPath($endPath);

        if ($endDriveLetter && $startDriveLetter && $endDriveLetter != $startDriveLetter) {
            // End path is on another drive, so no relative path exists
            return $endDriveLetter.':/'.($endPathArr ? implode('/', $endPathArr).'/' : '');
        }

        // Find for which directory the common path stops
        $index = 0;
        while (isset($startPathArr[$index]) && isset($endPathArr[$index]) && $startPathArr[$index] === $endPathArr[$index]) {
            ++$index;
        }

        // Determine how deep the start path is relative to the common path (ie, "web/bundles" = 2 levels)
        if (1 === \count($startPathArr) && '' === $startPathArr[0]) {
            $depth = 0;
        } else {
            $depth = \count($startPathArr) - $index;
        }

        // Repeated "../" for each level need to reach the common path
        $traverser = str_repeat('../', $depth);

        $endPathRemainder = implode('/', \array_slice($endPathArr, $index));

        // Construct $endPath from traversing to the common path, then to the remaining $endPath
        $relativePath = $traverser.('' !== $endPathRemainder ? $endPathRemainder.'/' : '');

        return '' === $relativePath ? './' : $relativePath;
    }

    /**
     * Mirrors a directory to another.
     *
     * Copies files and directories from the origin directory into the target directory. By default:
     *
     *  - existing files in the target directory will be overwritten, except if they are newer (see the `override` option)
     *  - files in the target directory that do not exist in the source directory will not be deleted (see the `delete` option)
     *
     * @param \Traversable|null $iterator Iterator that filters which files and directories to copy, if null a recursive iterator is created
     * @param array             $options  An array of boolean options
     *                                    Valid options are:
     *                                    - $options['override'] If true, target files newer than origin files are overwritten (see copy(), defaults to false)
     *                                    - $options['copy_on_windows'] Whether to copy files instead of links on Windows (see symlink(), defaults to false)
     *                                    - $options['delete'] Whether to delete files that are not in the source directory (defaults to false)
     *
     * @return void
     *
     * @throws IOException When file type is unknown
     */
    public function mirror(string $originDir, string $targetDir, ?\Traversable $iterator = null, array $options = [])
    {
        $targetDir = rtrim($targetDir, '/\\');
        $originDir = rtrim($originDir, '/\\');
        $originDirLen = \strlen($originDir);

        if (!$this->exists($originDir)) {
            throw new IOException(sprintf('The origin directory specified "%s" was not found.', $originDir), 0, null, $originDir);
        }

        // Iterate in destination folder to remove obsolete entries
        if ($this->exists($targetDir) && isset($options['delete']) && $options['delete']) {
            $deleteIterator = $iterator;
            if (null === $deleteIterator) {
                $flags = \FilesystemIterator::SKIP_DOTS;
                $deleteIterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($targetDir, $flags), \RecursiveIteratorIterator::CHILD_FIRST);
            }
            $targetDirLen = \strlen($targetDir);
            foreach ($deleteIterator as $file) {
                $origin = $originDir.substr($file->getPathname(), $targetDirLen);
                if (!$this->exists($origin)) {
                    $this->remove($file);
                }
            }
        }

        $copyOnWindows = $options['copy_on_windows'] ?? false;

        if (null === $iterator) {
            $flags = $copyOnWindows ? \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS : \FilesystemIterator::SKIP_DOTS;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($originDir, $flags), \RecursiveIteratorIterator::SELF_FIRST);
        }

        $this->mkdir($targetDir);
        $filesCreatedWhileMirroring = [];

        foreach ($iterator as $file) {
            if ($file->getPathname() === $targetDir || $file->getRealPath() === $targetDir || isset($filesCreatedWhileMirroring[$file->getRealPath()])) {
                continue;
            }

            $target = $targetDir.substr($file->getPathname(), $originDirLen);
            $filesCreatedWhileMirroring[$target] = true;

            if (!$copyOnWindows && is_link($file)) {
                $this->symlink($file->getLinkTarget(), $target);
            } elseif (is_dir($file)) {
                $this->mkdir($target);
            } elseif (is_file($file)) {
                $this->copy($file, $target, $options['override'] ?? false);
            } else {
                throw new IOException(sprintf('Unable to guess "%s" file type.', $file), 0, null, $file);
            }
        }
    }

    /**
     * Returns whether the file path is an absolute path.
     */
    public function isAbsolutePath(string $file): bool
    {
        return '' !== $file && (strspn($file, '/\\', 0, 1)
            || (\strlen($file) > 3 && ctype_alpha($file[0])
                && ':' === $file[1]
                && strspn($file, '/\\', 2, 1)
            )
            || null !== parse_url($file, \PHP_URL_SCHEME)
        );
    }

    /**
     * Creates a temporary file with support for custom stream wrappers.
     *
     * @param string $prefix The prefix of the generated temporary filename
     *                       Note: Windows uses only the first three characters of prefix
     * @param string $suffix The suffix of the generated temporary filename
     *
     * @return string The new temporary filename (with path), or throw an exception on failure
     */
    public function tempnam(string $dir, string $prefix, string $suffix = ''): string
    {
        [$scheme, $hierarchy] = $this->getSchemeAndHierarchy($dir);

        // If no scheme or scheme is "file" or "gs" (Google Cloud) create temp file in local filesystem
        if ((null === $scheme || 'file' === $scheme || 'gs' === $scheme) && '' === $suffix) {
            // If tempnam failed or no scheme return the filename otherwise prepend the scheme
            if ($tmpFile = self::box('tempnam', $hierarchy, $prefix)) {
                if (null !== $scheme && 'gs' !== $scheme) {
                    return $scheme.'://'.$tmpFile;
                }

                return $tmpFile;
            }

            throw new IOException('A temporary file could not be created: '.self::$lastError);
        }

        // Loop until we create a valid temp file or have reached 10 attempts
        for ($i = 0; $i < 10; ++$i) {
            // Create a unique filename
            $tmpFile = $dir.'/'.$prefix.uniqid(mt_rand(), true).$suffix;

            // Use fopen instead of file_exists as some streams do not support stat
            // Use mode 'x+' to atomically check existence and create to avoid a TOCTOU vulnerability
            if (!$handle = self::box('fopen', $tmpFile, 'x+')) {
                continue;
            }

            // Close the file if it was successfully opened
            self::box('fclose', $handle);

            return $tmpFile;
        }

        throw new IOException('A temporary file could not be created: '.self::$lastError);
    }

    /**
     * Atomically dumps content into a file.
     *
     * @param string|resource $content The data to write into the file
     *
     * @return void
     *
     * @throws IOException if the file cannot be written to
     */
    public function dumpFile(string $filename, $content)
    {
        if (\is_array($content)) {
            throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be string or resource, array given.', __METHOD__));
        }

        $dir = \dirname($filename);

        if (is_link($filename) && $linkTarget = $this->readlink($filename)) {
            $this->dumpFile(Path::makeAbsolute($linkTarget, $dir), $content);

            return;
        }

        if (!is_dir($dir)) {
            $this->mkdir($dir);
        }

        // Will create a temp file with 0600 access rights
        // when the filesystem supports chmod.
        $tmpFile = $this->tempnam($dir, basename($filename));

        try {
            if (false === self::box('file_put_contents', $tmpFile, $content)) {
                throw new IOException(sprintf('Failed to write file "%s": ', $filename).self::$lastError, 0, null, $filename);
            }

            self::box('chmod', $tmpFile, @fileperms($filename) ?: 0666 & ~umask());

            $this->rename($tmpFile, $filename, true);
        } finally {
            if (file_exists($tmpFile)) {
                self::box('unlink', $tmpFile);
            }
        }
    }

    /**
     * Appends content to an existing file.
     *
     * @param string|resource $content The content to append
     * @param bool            $lock    Whether the file should be locked when writing to it
     *
     * @return void
     *
     * @throws IOException If the file is not writable
     */
    public function appendToFile(string $filename, $content/* , bool $lock = false */)
    {
        if (\is_array($content)) {
            throw new \TypeError(sprintf('Argument 2 passed to "%s()" must be string or resource, array given.', __METHOD__));
        }

        $dir = \dirname($filename);

        if (!is_dir($dir)) {
            $this->mkdir($dir);
        }

        $lock = \func_num_args() > 2 && func_get_arg(2);

        if (false === self::box('file_put_contents', $filename, $content, \FILE_APPEND | ($lock ? \LOCK_EX : 0))) {
            throw new IOException(sprintf('Failed to write file "%s": ', $filename).self::$lastError, 0, null, $filename);
        }
    }

    private function toIterable(string|iterable $files): iterable
    {
        return is_iterable($files) ? $files : [$files];
    }

    /**
     * Gets a 2-tuple of scheme (may be null) and hierarchical part of a filename (e.g. file:///tmp -> [file, tmp]).
     */
    private function getSchemeAndHierarchy(string $filename): array
    {
        $components = explode('://', $filename, 2);

        return 2 === \count($components) ? [$components[0], $components[1]] : [null, $components[0]];
    }

    private static function assertFunctionExists(string $func): void
    {
        if (!\function_exists($func)) {
            throw new IOException(sprintf('Unable to perform filesystem operation because the "%s()" function has been disabled.', $func));
        }
    }

    private static function box(string $func, mixed ...$args): mixed
    {
        self::assertFunctionExists($func);

        self::$lastError = null;
        set_error_handler(self::handleError(...));
        try {
            return $func(...$args);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @internal
     */
    public static function handleError(int $type, string $msg): void
    {
        self::$lastError = $msg;
    }
}
