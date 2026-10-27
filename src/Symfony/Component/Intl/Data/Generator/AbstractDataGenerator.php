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
    public function __construct(
        private BundleCompilerInterface $compiler,
        private string $dirName,
    ) {
    }

    public function generateData(GeneratorConfig $config): void
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

        $data = [];

        foreach ($locales as $locale) {
            if (null !== $localeData = $this->generateDataForLocale($reader, $tempDir, $locale)) {
                $data[$locale] = $localeData;
            }
        }

        if (null !== $rootData = $this->generateDataForRoot($reader, $tempDir)) {
            $data['root'] = $rootData;
        }

        $metaData = $this->generateDataForMeta($reader, $tempDir);

        // Store names at the position of their code in a shared index, so that opcache can store them as packed arrays
        $index = [];

        foreach ($data as $localeData) {
            $index += $localeData['Names'] ?? [];
        }

        if ($index) {
            ksort($index, \SORT_STRING);
            $index = array_flip(array_keys($index));
            $metaData['Index'] = $index;

            foreach ($data as $locale => $localeData) {
                // Currency symbols use the same index as currency names
                foreach (['Names', 'Symbols'] as $key) {
                    if (isset($localeData[$key])) {
                        $values = [];

                        foreach ($localeData[$key] as $code => $value) {
                            $values[$index[$code]] = $value;
                        }

                        ksort($values);
                        $data[$locale][$key] = $values;
                    }
                }
            }
        }

        if (null !== $metaData) {
            $data['meta'] = $metaData;
        }

        foreach ($data as $locale => $localeData) {
            foreach ($writers as $targetDir => $writer) {
                $writer->write($targetDir.'/'.$this->dirName, $locale, $localeData);
            }
        }

        // Clean up
        $filesystem->remove($tempDir);
    }

    /**
     * @return string[]
     */
    abstract protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array;

    abstract protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir): void;

    abstract protected function preGenerate(): void;

    abstract protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array;

    abstract protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array;

    abstract protected function generateDataForMeta(BundleEntryReaderInterface $reader, string $tempDir): ?array;
}
