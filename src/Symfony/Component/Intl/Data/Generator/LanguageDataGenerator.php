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
use Symfony\Component\Intl\Data\Util\ArrayAccessibleResourceBundle;
use Symfony\Component\Intl\Data\Util\LocaleScanner;
use Symfony\Component\Intl\Exception\RuntimeException;

/**
 * The rule for compiling the language bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @internal
 */
class LanguageDataGenerator extends AbstractDataGenerator
{
    /**
     * Source: https://iso639-3.sil.org/code_tables/639/data.
     */
    private static $preferredAlpha2ToAlpha3Mapping = [
        'ak' => 'aka',
        'ar' => 'ara',
        'ay' => 'aym',
        'az' => 'aze',
        'bo' => 'bod',
        'cr' => 'cre',
        'cs' => 'ces',
        'cy' => 'cym',
        'de' => 'deu',
        'dz' => 'dzo',
        'el' => 'ell',
        'et' => 'est',
        'eu' => 'eus',
        'fa' => 'fas',
        'ff' => 'ful',
        'fr' => 'fra',
        'gn' => 'grn',
        'hy' => 'hye',
        'hr' => 'hrv',
        'ik' => 'ipk',
        'is' => 'isl',
        'iu' => 'iku',
        'ka' => 'kat',
        'kr' => 'kau',
        'kg' => 'kon',
        'kv' => 'kom',
        'ku' => 'kur',
        'lv' => 'lav',
        'mg' => 'mlg',
        'mi' => 'mri',
        'mk' => 'mkd',
        'mn' => 'mon',
        'ms' => 'msa',
        'my' => 'mya',
        'nb' => 'nob',
        'ne' => 'nep',
        'nl' => 'nld',
        'oj' => 'oji',
        'om' => 'orm',
        'or' => 'ori',
        'ps' => 'pus',
        'qu' => 'que',
        'ro' => 'ron',
        'sc' => 'srd',
        'sk' => 'slk',
        'sq' => 'sqi',
        'sr' => 'srp',
        'sw' => 'swa',
        'uz' => 'uzb',
        'yi' => 'yid',
        'za' => 'zha',
        'zh' => 'zho',
    ];

    /**
     * Collects all available language codes.
     *
     * @var string[]
     */
    private $languageCodes = [];

    /**
     * {@inheritdoc}
     */
    protected function scanLocales(LocaleScanner $scanner, $sourceDir)
    {
        return $scanner->scanLocales($sourceDir.'/lang');
    }

    /**
     * {@inheritdoc}
     */
    protected function compileTemporaryBundles(BundleCompilerInterface $compiler, $sourceDir, $tempDir)
    {
        $compiler->compile($sourceDir.'/lang', $tempDir);
        $compiler->compile($sourceDir.'/misc/metadata.txt', $tempDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->languageCodes = [];
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleEntryReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        // isset() on \ResourceBundle returns true even if the value is null
        if (isset($localeBundle['Languages']) && null !== $localeBundle['Languages']) {
            $data = [
                'Names' => iterator_to_array($localeBundle['Languages']),
            ];

            $this->languageCodes = array_merge($this->languageCodes, array_keys($data['Names']));

            return $data;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleEntryReaderInterface $reader, $tempDir)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleEntryReaderInterface $reader, $tempDir)
    {
        $metadataBundle = $reader->read($tempDir, 'metadata');

        $this->languageCodes = array_unique($this->languageCodes);

        sort($this->languageCodes);

        return [
            'Languages' => $this->languageCodes,
            'Alpha2ToAlpha3' => $this->generateAlpha2ToAlpha3Mapping($metadataBundle),
        ];
    }

    private function generateAlpha2ToAlpha3Mapping(ArrayAccessibleResourceBundle $metadataBundle)
    {
        $aliases = iterator_to_array($metadataBundle['alias']['language']);
        $alpha2ToAlpha3 = [];

        foreach ($aliases as $alias => $data) {
            $language = $data['replacement'];
            if (2 === \strlen($language) && 3 === \strlen($alias) && 'overlong' === $data['reason']) {
                if (isset(self::$preferredAlpha2ToAlpha3Mapping[$language])) {
                    // Validate to prevent typos
                    if (!isset($aliases[self::$preferredAlpha2ToAlpha3Mapping[$language]])) {
                        throw new RuntimeException('The statically set three-letter mapping '.self::$preferredAlpha2ToAlpha3Mapping[$language].' for the language code '.$language.' seems to be invalid. Typo?');
                    }

                    $alpha3 = self::$preferredAlpha2ToAlpha3Mapping[$language];
                    $alpha2 = $aliases[$alpha3]['replacement'];

                    if ($language !== $alpha2) {
                        throw new RuntimeException('The statically set three-letter mapping '.$alpha3.' for the language code '.$language.' seems to be an alias for '.$alpha2.'. Wrong mapping?');
                    }

                    $alpha2ToAlpha3[$language] = $alpha3;
                } elseif (isset($alpha2ToAlpha3[$language])) {
                    throw new RuntimeException('Multiple three-letter mappings exist for the language code '.$language.'. Please add one of them to the property $preferredAlpha2ToAlpha3Mapping.');
                } else {
                    $alpha2ToAlpha3[$language] = $alias;
                }
            }
        }

        asort($alpha2ToAlpha3);

        return $alpha2ToAlpha3;
    }
}
