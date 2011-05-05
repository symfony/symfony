<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\File;

use Symfony\Component\HttpFoundation\File\Exception\DirectoryCreationException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class TemporaryStorage
{
    private $directory;
    private $secret;
    private $size;
    private $ttlMin;

    /**
     * Constructor.
     *
     * @param string   $secret      A secret
     * @param sting    $directory   The base directory
     * @param integer  $size        The maximum size for the temporary storage (in Bytes)
     *                              Should be set to 0 for an unlimited size.
     * @param integer  $ttlMin      The time to live in minutes (a positive number)
     *                              Should be set to 0 for an infinite ttl
     *
     * @throws DirectoryCreationException if the directory does not exist or fails to be created
     */
    public function __construct($secret, $directory, $size = 0, $ttlMin = 0)
    {
        if (!is_dir($directory)) {
            if (file_exists($directory) || false === mkdir($directory, 0777, true)) {
                throw new DirectoryCreationException(($directory));
            }
        }

        $this->directory = realpath($directory);
        $this->secret = $secret;
        $this->size = max((int) $size, 0);
        $this->ttlMin = max((int) $ttlMin, 0);

        $this->truncate();
    }

    protected function generateHashInfo($token)
    {
        return $this->secret.$token;
    }

    protected function generateHash($token)
    {
        return md5($this->generateHashInfo($token));
    }

    /**
     * Creates the directory associated with the given token.
     *
     * The directory is created when it does not exists.
     *
     * @param string $token A token
     *
     * @return string The directory name
     *
     * @throws UnexpectedTypeException if the token is not a string
     * @throws DirectoryCreationException if the directory does not exist or fails to be created
     */
    public function getTempDir($token)
    {
        if (!is_string($token)) {
            throw new UnexpectedTypeException($token, 'string');
        }

        $hash = $this->generateHash($token);

        $directory = $this->directory.DIRECTORY_SEPARATOR.substr($hash, 0, 2).DIRECTORY_SEPARATOR.substr($hash, 2);

        if (!is_dir($directory)) {
            if (file_exists($directory) || false === mkdir($directory, 0777, true)) {
                throw new DirectoryCreationException($directory);
            }
        }

        return $directory;
    }

    /**
     * Truncates the temporary storage folder to its maximum size.
     *
     * @return Boolean true when some files had to be deleted
     *
     * @throws FileException if a problem occurs while deleting a file
     */
    public function truncate()
    {
        $truncated = false;

        if (0 == $this->size && 0 == $this->ttlMin) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->directory,\RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $files = array();
        $size = 0;
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
                $files[$file->getRealPath()] = array(
                  'size'  => $file->getSize(),
                  'mtime' => $file->getMTime(),
                );
            }
        }

        if ($this->ttlMin > 0) {
            $keepAfter = time() - 60 * $this->ttlMin;
            foreach ($files as $path => $file) {
                if ($file['mtime'] < $keepAfter) {
                    $truncated = true;
                    if (false === @unlink($path)) {
                        throw new FileException(sprintf('Unable to delete the file "%s"', $path));
                    }
                    $size -= $file['size'];
                }
            }
        }

        if ($this->size > 0) {
            uasort($files, function($f1, $f2) { return $f1['mtime'] > $f2['mtime']; });
            $file = reset($files);
            while ($size > $this->size) {
                $truncated = true;
                $path = key($files);
                if (false === @unlink($path)) {
                    throw new FileException(sprintf('Unable to delete the file "%s"', $path));
                }
                $size -= $file['size'];
                $file = next($files);
            }
        }

        return $truncated;
    }
}
