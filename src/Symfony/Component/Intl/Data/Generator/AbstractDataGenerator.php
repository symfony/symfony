<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Data\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Intl\Data\Bundle\Compiler\GenrbCompiler;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleReaderInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\IntlBundleReader;
use Symfony\Component\Intl\Data\Util\LocaleScanner;

/**
 * The rule for compiling the currency bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
abstract class AbstractDataGenerator
{
    private $compiler;
    private $dirName;

    public function __construct(GenrbCompiler $compiler, $dirName)
    {
        $this->compiler = $compiler;
        $this->dirName = (string) $dirName;
    }

    public function generateData(GeneratorConfig $config)
    {
        $filesystem = new Filesystem();
        $localeScanner = new LocaleScanner();
        $reader = new IntlBundleReader();

        $writers = $config->getBundleWriters();
        $tempDir = sys_get_temp_dir().'/icu-data-'.$this->dirName;

        // Prepare filesystem directories
        foreach ($writers as $targetDir => $writer) {
            $filesystem->remove($targetDir.'/'.$this->dirName);
            $filesystem->mkdir($targetDir.'/'.$this->dirName);
        }

        $filesystem->remove($tempDir);
        $filesystem->mkdir($tempDir);

        $locales = $this->scanLocales($localeScanner, $config->getSourceDir());

        $this->compileTemporaryBundles($this->compiler, $config->getSourceDir(), $tempDir);

        $this->preGenerate();

        foreach ($locales as $locale) {
            $localeData = $this->generateDataForLocale($reader, $tempDir, $locale);

            if (null !== $localeData) {
                foreach ($writers as $targetDir => $writer) {
                    $writer->write($targetDir.'/'.$this->dirName, $locale, $localeData);
                }
            }
        }

        $rootData = $this->generateDataForRoot($reader, $tempDir);

        if (null !== $rootData) {
            foreach ($writers as $targetDir => $writer) {
                $writer->write($targetDir.'/'.$this->dirName, 'root', $rootData);
            }
        }

        $metaData = $this->generateDataForMeta($reader, $tempDir);

        if (null !== $metaData) {
            foreach ($writers as $targetDir => $writer) {
                $writer->write($targetDir.'/'.$this->dirName, 'meta', $metaData);
            }
        }

        // Clean up
        $filesystem->remove($tempDir);
    }

    /**
     * @param LocaleScanner $scanner
     * @param string        $sourceDir
     *
     * @return string[]
     */
    abstract protected function scanLocales(LocaleScanner $scanner, $sourceDir);

    /**
     * @param GenrbCompiler $compiler
     * @param string        $sourceDir
     * @param string        $tempDir
     */
    abstract protected function compileTemporaryBundles(GenrbCompiler $compiler, $sourceDir, $tempDir);

    abstract protected function preGenerate();

    /**
     * @param BundleReaderInterface $reader
     * @param string                $tempDir
     * @param string                $displayLocale
     *
     * @return array|null
     */
    abstract protected function generateDataForLocale(BundleReaderInterface $reader, $tempDir, $displayLocale);

    /**
     * @param BundleReaderInterface $reader
     * @param string                $tempDir
     *
     * @return array|null
     */
    abstract protected function generateDataForRoot(BundleReaderInterface $reader, $tempDir);

    /**
     * @param BundleReaderInterface $reader
     * @param string                $tempDir
     *
     * @return array|null
     */
    abstract protected function generateDataForMeta(BundleReaderInterface $reader, $tempDir);
}
