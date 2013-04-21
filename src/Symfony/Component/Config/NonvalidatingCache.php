<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A cache that never expires once it has been written.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class NonvalidatingCache
{
    protected $file;

    /**
     * Constructor.
     *
     * @param string  $file  The absolute cache path
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->file;
    }

    /**
     * By design, this method always returns true once the cache
     * has been initialized.
     *
     * {@inheritdoc}
     */
    public function isFresh()
    {
        if (!is_file($this->file)) {
            return false;
        }

        return true;
    }

    /**
     * This implementation ignores the metadata.
     * {@inheritdoc}
     */
    public function write($content, array $metadata = null)
    {
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->file, $content, 0666 & ~umask());
    }
}
