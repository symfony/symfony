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

use Symfony\Component\Intl\Data\Bundle\Compiler\GenrbCompiler;
use Symfony\Component\Intl\Data\Bundle\Reader\BundleReaderInterface;
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
     * Source: http://www-01.sil.org/iso639-3/codes.asp.
     */
    private static $preferredAlpha2ToAlpha3Mapping = array(
        'ak' => 'aka',
        'ar' => 'ara',
        'ay' => 'aym',
        'az' => 'aze',
        'bo' => 'bod',
        'cr' => 'cre',
        'cs' => 'ces',
        'cy' => 'cym',
        'de' => 'deu',
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
    );

    /**
     * Collects all available language codes.
     *
     * @var string[]
     */
    private $languageCodes = array();

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
    protected function compileTemporaryBundles(GenrbCompiler $compiler, $sourceDir, $tempDir)
    {
        $compiler->compile($sourceDir.'/lang', $tempDir);
        $compiler->compile($sourceDir.'/misc/metadata.txt', $tempDir);
    }

    /**
     * {@inheritdoc}
     */
    protected function preGenerate()
    {
        $this->languageCodes = array();
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForLocale(BundleReaderInterface $reader, $tempDir, $displayLocale)
    {
        $localeBundle = $reader->read($tempDir, $displayLocale);

        // isset() on \ResourceBundle returns true even if the value is null
        if (isset($localeBundle['Languages']) && null !== $localeBundle['Languages']) {
            $data = array(
                'Version' => $localeBundle['Version'],
                'Names' => iterator_to_array($localeBundle['Languages']),
            );

            $this->languageCodes = array_merge($this->languageCodes, array_keys($data['Names']));

            return $data;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForRoot(BundleReaderInterface $reader, $tempDir)
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function generateDataForMeta(BundleReaderInterface $reader, $tempDir)
    {
        $rootBundle = $reader->read($tempDir, 'root');
        $metadataBundle = $reader->read($tempDir, 'metadata');

        $this->languageCodes = array_unique($this->languageCodes);

        sort($this->languageCodes);

        return array(
            'Version' => $rootBundle['Version'],
            'Languages' => $this->languageCodes,
            'Aliases' => array_column(iterator_to_array($metadataBundle['alias']['language']), 'replacement'),
            'Alpha2ToAlpha3' => $this->generateAlpha2ToAlpha3Mapping($metadataBundle),
        );
    }

    private function generateAlpha2ToAlpha3Mapping(ArrayAccessibleResourceBundle $metadataBundle)
    {
        $aliases = iterator_to_array($metadataBundle['alias']['language']);
        $alpha2ToAlpha3 = array();

        foreach ($aliases as $alias => $language) {
            $language = $language['replacement'];
            if (2 === \strlen($language) && 3 === \strlen($alias)) {
                if (isset(self::$preferredAlpha2ToAlpha3Mapping[$language])) {
                    // Validate to prevent typos
                    if (!isset($aliases[self::$preferredAlpha2ToAlpha3Mapping[$language]])) {
                        throw new RuntimeException(
                            'The statically set three-letter mapping '.
                            self::$preferredAlpha2ToAlpha3Mapping[$language].' '.
                            'for the language code '.$language.' seems to be '.
                            'invalid. Typo?'
                        );
                    }

                    $alpha3 = self::$preferredAlpha2ToAlpha3Mapping[$language];
                    $alpha2 = $aliases[$alpha3]['replacement'];

                    if ($language !== $alpha2) {
                        throw new RuntimeException(
                            'The statically set three-letter mapping '.$alpha3.' '.
                            'for the language code '.$language.' seems to be '.
                            'an alias for '.$alpha2.'. Wrong mapping?'
                        );
                    }

                    $alpha2ToAlpha3[$language] = $alpha3;
                } elseif (isset($alpha2ToAlpha3[$language])) {
                    throw new RuntimeException(
                        'Multiple three-letter mappings exist for the language '.
                        'code '.$language.'. Please add one of them to the '.
                        'property $preferredAlpha2ToAlpha3Mapping.'
                    );
                } else {
                    $alpha2ToAlpha3[$language] = $alias;
                }
            }
        }

        return $alpha2ToAlpha3;
    }
}
