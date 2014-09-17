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
use Symfony\Component\Intl\ResourceBundle\Compiler\BundleCompilerInterface;

/**
 * Default implementation of {@link CompilationContextInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class CompilationContext implements CompilationContextInterface
{
    /**
     * @var string
     */
    private $sourceDir;

    /**
     * @var string
     */
    private $binaryDir;

    /**
     * @var FileSystem
     */
    private $filesystem;

    /**
     * @var BundleCompilerInterface
     */
    private $compiler;

    /**
     * @var string
     */
    private $icuVersion;

    public function __construct($sourceDir, $binaryDir, Filesystem $filesystem, BundleCompilerInterface $compiler, $icuVersion)
    {
        $this->sourceDir = $sourceDir;
        $this->binaryDir = $binaryDir;
        $this->filesystem = $filesystem;
        $this->compiler = $compiler;
        $this->icuVersion = $icuVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceDir()
    {
        return $this->sourceDir;
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
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompiler()
    {
        return $this->compiler;
    }

    /**
     * {@inheritdoc}
     */
    public function getIcuVersion()
    {
        return $this->icuVersion;
    }
}
