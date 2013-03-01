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
 * FileResource represents a resource stored on the filesystem.
 *
 * The resource can be a file or a directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FileResource implements SelfValidatingResourceInterface
{
    private $filename;
    private $mtime;

    /**
     * Constructor.
     *
     * @param string $filename The file path to the resource
     */
    public function __construct($filename)
    {
        $this->filename = realpath($filename);
        $this->mtime = filemtime($this->filename);
    }

    public function isFresh()
    {
        if (!file_exists($this->filename)) {
            return false;
        }

        return filemtime($this->filename) == $this->mtime;
    }

    public function serialize()
    {
        return serialize(array(
            'filename' => $this->filename,
            'mtime' => $this->mtime
        ));
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->filename = $data['filename'];
        $this->mtime = $data['mtime'];
    }
}
