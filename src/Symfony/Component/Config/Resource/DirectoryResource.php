<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * DirectoryResource represents a resources stored in a subdirectory tree.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DirectoryResource implements SelfValidatingResourceInterface
{
    private $directory;
    private $pattern;
    private $hash = '';

    /**
     * Constructor.
     *
     * @param string $directory The file path to the resource
     * @param string $pattern   A pattern to restrict monitored files
     */
    public function __construct($directory, $pattern = null)
    {
        $this->directory = $directory;
        $this->hash = $this->calculateHash();
        $this->pattern = $pattern;
    }

    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * Returns true if the resource has not been updated since the given timestamp.
     *
     * @param integer $timestamp The last time the resource was loaded
     * @return Boolean true if the resource has not been updated, false otherwise
     */
    public function isFresh()
    {
        return $this->calculateHash() == $this->hash;
    }

    protected function calculateHash()
    {
        $hash = '';

        if (is_dir($this->directory)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory), \RecursiveIteratorIterator::SELF_FIRST) as $file) {

                // only check files, because directories change state also if files not matching the pattern are added/deleted
                if ($file->isDir()) {
                    continue;
                }

                // if regex filtering is enabled only check matching files
                if ($this->pattern && !preg_match($this->pattern, $file->getBasename())) {
                    continue;
                }

                $hash = md5($hash .  '-' . $file . '-' . $file->getMTime());
            }
        }

        return $hash;
    }

    public function serialize()
    {
        return serialize(array(
            'directory' => $this->directory,
            'pattern' => $this->pattern,
            'hash' => $this->hash
        ));
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->directory = $data['directory'];
        $this->pattern = $data['pattern'];
        $this->hash = $data['hash'];
    }

}
