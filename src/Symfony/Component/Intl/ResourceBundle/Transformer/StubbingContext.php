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
 *
 * @since v2.3.0
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

    /**
     * @since v2.3.0
     */
    public function __construct($binaryDir, $stubDir, Filesystem $filesystem, $icuVersion)
    {
        $this->binaryDir = $binaryDir;
        $this->stubDir = $stubDir;
        $this->filesystem = $filesystem;
        $this->icuVersion = $icuVersion;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getBinaryDir()
    {
        return $this->binaryDir;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getStubDir()
    {
        return $this->stubDir;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getIcuVersion()
    {
        return $this->icuVersion;
    }
}
