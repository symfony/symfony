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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class StubbingContext implements StubbingContextInterface
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
     * {@inheritdoc}
     */
    public function getBinaryDir()
    {
        return $this->binaryDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getStubDir()
    {
        return $this->stubDir;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function getIcuVersion()
    {
        return $this->icuVersion;
    }
}
