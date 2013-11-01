<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Transformer;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Stores contextual information for resource bundle stub creation.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class StubbingContext
{
    /**
     * @var string
     */
    private $binaryDir;

    /**
     * @var string
     */
    private $stubDir;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $icuVersion;

    public function __construct($binaryDir, $stubDir, Filesystem $filesystem, $icuVersion)
    {
        $this->binaryDir = $binaryDir;
        $this->stubDir = $stubDir;
        $this->filesystem = $filesystem;
        $this->icuVersion = $icuVersion;
    }

    /**
     * Returns the directory where the binary resource bundles are stored.
     *
     * @return string An absolute path to a directory.
     */
    public function getBinaryDir()
    {
        return $this->binaryDir;
    }

    /**
     * Returns the directory where the stub resource bundles are stored.
     *
     * @return string An absolute path to a directory.
     */
    public function getStubDir()
    {
        return $this->stubDir;
    }

    /**
     * Returns a tool for manipulating the filesystem.
     *
     * @return \Symfony\Component\Filesystem\Filesystem The filesystem manipulator.
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * Returns the ICU version of the bundles being converted.
     *
     * @return string The ICU version string.
     */
    public function getIcuVersion()
    {
        return $this->icuVersion;
    }
}
