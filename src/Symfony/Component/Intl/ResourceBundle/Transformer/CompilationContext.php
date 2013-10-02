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
use Symfony\Component\Intl\ResourceBundle\Scanner\LocaleScanner;

/**
 * Default implementation of {@link CompilationContextInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
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
     * @var Filesystem
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

    /**
     * @var LocaleScanner
     */
    private $localeScanner;

    public function __construct($sourceDir, $binaryDir, Filesystem $filesystem, BundleCompilerInterface $compiler, $icuVersion, LocaleScanner $localeScanner = null)
    {
        $this->sourceDir = $sourceDir;
        $this->binaryDir = $binaryDir;
        $this->filesystem = $filesystem;
        $this->compiler = $compiler;
        $this->icuVersion = $icuVersion;
        $this->localeScanner = $localeScanner ?: new LocaleScanner();
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

    /**
     * {@inheritdoc}
     */
    public function getLocaleScanner()
    {
        return $this->localeScanner;
    }
}
