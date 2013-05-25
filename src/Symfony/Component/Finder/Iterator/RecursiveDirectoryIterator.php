<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\SplFileInfo;

/**
 * Extends the \RecursiveDirectoryIterator to support relative paths
 *
 * @author Victor Berchet <victor@suumit.com>
 */
class RecursiveDirectoryIterator extends \RecursiveDirectoryIterator
{
    /**
     * @var Boolean
     */
    private $rewindable;

    public function __construct($path, $flags)
    {
        if ($flags & (self::CURRENT_AS_PATHNAME | self::CURRENT_AS_SELF)) {
            throw new \RuntimeException('This iterator only support returning current as fileinfo.');
        }

        parent::__construct($path, $flags);
    }

    /**
     * Return an instance of SplFileInfo with support for relative paths
     *
     * @return SplFileInfo File information
     */
    public function current()
    {
        return new SplFileInfo(parent::current()->getPathname(), $this->getSubPath(), $this->getSubPathname());
    }

    /**
     * Do nothing for non rewindable stream
     */
    public function rewind()
    {
        if (false === $this->isRewindable()) {
            return;
        }

        // @see https://bugs.php.net/bug.php?id=49104
        parent::next();

        parent::rewind();
    }

    /**
     * Checks if the stream is rewindable.
     *
     * @return Boolean true when the stream is rewindable, false otherwise
     */
    public function isRewindable()
    {
        if (null !== $this->rewindable) {
            return $this->rewindable;
        }

        if (false !== $stream = @opendir($this->getPath())) {
            $infos = stream_get_meta_data($stream);
            closedir($stream);

            if ($infos['seekable']) {
                return $this->rewindable = true;
            }
        }

        return $this->rewindable = false;
    }
}
