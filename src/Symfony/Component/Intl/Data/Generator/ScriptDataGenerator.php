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

/**
 * The rule for compiling the script bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class ScriptDataGenerator extends AbstractDataGenerator
{
    private const DENYLIST = [
        'Zzzz' => true, // Unknown Script
    ];

    /**
     * Collects all available language codes.
     *
     * @var string[]
     */
    private array $scriptCodes = [];

    protected function scanLocales(LocaleScanner $scanner, string $sourceDir): array
    {
        return $scanner->scanLocales($sourceDir.'/lang');
    }

    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, string $sourceDir, string $tempDir)
    {
        $compiler->compile($sourceDir.'/lang', $tempDir);
    }

    protected function preGenerate()
    {
        $this->scriptCodes = [];
    }

    protected function generateDataForLocale(BundleEntryReaderInterface $reader, string $tempDir, string $displayLocale): ?array
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        // isset() on \ResourceBundle returns true even if the value is null
        if (isset($localeBundle['Scripts']) && null !== $localeBundle['Scripts']) {
            $data = [
                'Names' => array_diff_key(iterator_to_array($localeBundle['Scripts']), self::DENYLIST),
            ];

            $this->scriptCodes = array_merge($this->scriptCodes, array_keys($data['Names']));

            return $data;
        }

        return null;
    }

    protected function generateDataForRoot(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        return null;
    }

    protected function generateDataForMeta(BundleEntryReaderInterface $reader, string $tempDir): ?array
    {
        $this->scriptCodes = array_unique($this->scriptCodes);

        sort($this->scriptCodes);

        return [
            'Scripts' => $this->scriptCodes,
        ];
    }
}
