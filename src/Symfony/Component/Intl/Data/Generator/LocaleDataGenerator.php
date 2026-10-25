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

use Symfony\Component\Intl\Data\Bundle\Compiler\BundleCompilerInterface;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleEntryReaderInterface;
use Symfony\Component\Intl\Data\Util\LocaleScanner;
use Symfony\Component\VarExporter\VarExporter;

/**
 * The rule for compiling the locale bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Roland Franssen <franssen.roland@gmail.com>
 *
 * @internal
 */
class LocaleDataGenerator extends AbstractDataGenerator
{
    private array $locales = [];
    private array $localeAliases = [];
    private array $localeParents = [];

    protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array
    {
        $this->locales = $scanner->scanLocales($sourceDir.'/locales');
        $this->localeAliases = $scanner->scanAliases($sourceDir.'/locales');
        $this->localeParents = $scanner->scanParents($sourceDir.'/locales');

        return $this->locales;
    }

    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir): void
    {
    }

    protected function preGenerate(): void
    {
        // Write parents locale file for the Translation component
        file_put_contents(__DIR__.'/../../../Translation/Resources/data/parents.json', json_encode($this->localeParents, \JSON_PRETTY_PRINT)."\n");
        file_put_contents(__DIR__.'/../../../Translation/Resources/data/parents.php', "<?php\n\nreturn ".VarExporter::export($this->localeParents).";\n");
    }

    protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array
    {
        // Locale names are built during runtime from the names of their language, script and region,
        // using the patterns stored with the language names
        return null;
    }

    protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        return null;
    }

    protected function generateDataForMeta(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        return [
            'Locales' => $this->locales,
            'Parents' => $this->localeParents,
            'Aliases' => $this->localeAliases,
        ];
    }
}
