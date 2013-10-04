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
 * Stores contextual information for resource bundle compilation.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompilationContext
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
     * Returns the directory where the source versions of the resource bundles
     * are stored.
     *
     * @return string An absolute path to a directory.
     */
    public function getSourceDir()
    {
        return $this->sourceDir;
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
     * Returns a tool for manipulating the filesystem.
     *
     * @return \Symfony\Component\Filesystem\Filesystem The filesystem manipulator.
     */
    public function getFilesystem()
    {
        return $this->filesystem;
    }

    /**
     * Returns a resource bundle compiler.
     *
     * @return \Symfony\Component\Intl\ResourceBundle\Compiler\BundleCompilerInterface The loaded resource bundle compiler.
     */
    public function getCompiler()
    {
        return $this->compiler;
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

    /**
     * Returns a locale scanner.
     *
     * @return \Symfony\Component\Intl\ResourceBundle\Scanner\LocaleScanner The locale scanner.
     */
    public function getLocaleScanner()
    {
        return $this->localeScanner;
    }
}
