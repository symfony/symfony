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
use Symfony\Component\Intl\Data\Bundle\Compiler\BundleCompilerInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReader;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
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
    private BundleCompilerInterface $compiler;
    private string $dirName;

    public function __construct(BundleCompilerInterface $compiler, string $dirName)
    {
        $this->compiler = $compiler;
        $this->dirName = $dirName;
    }

    public function generateData(GeneratorConfig $config)
    {
        $filesystem = new Filesystem();
        $localeScanner = new LocaleScanner();
        $reader = new BundleEntryReader(new IntlBundleReader());

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
     * @return string[]
     */
    abstract protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array;

    abstract protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir);

    abstract protected function preGenerate();

    abstract protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array;

    abstract protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array;

    abstract protected function generateDataForMeta(BundleEntryReaderInterface $reader, string $tempDir): ?array;
}
