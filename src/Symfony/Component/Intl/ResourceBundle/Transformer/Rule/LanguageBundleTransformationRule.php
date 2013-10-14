<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Transformer\Rule;

use Symfony\Component\DependencyInjection\Tests\DefinitionDecoratorTest;
use Symfony\Component\Intl\Exception\RuntimeException;
use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\ResourceBundle\LanguageBundleInterface;
use Symfony\Component\Intl\ResourceBundle\Reader\BinaryBundleReader;
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContext;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContext;
use Symfony\Component\Intl\ResourceBundle\Writer\TextBundleWriter;
use Symfony\Component\Intl\Util\IcuVersion;

/**
 * The rule for compiling the language bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LanguageBundleTransformationRule implements TransformationRuleInterface
{
    /**
     * Source: http://www-01.sil.org/iso639-3/codes.asp
     *
     * @var array
     */
    private static $preferredAlpha2ToAlpha3Mapping = array(
        'ak' => 'aka',
        'ar' => 'ara',
        'ay' => 'aym',
        'az' => 'aze',
        'cr' => 'cre',
        'et' => 'est',
        'fa' => 'fas',
        'ff' => 'ful',
        'gn' => 'grn',
        'ik' => 'ipk',
        'iu' => 'iku',
        'kr' => 'kau',
        'kg' => 'kon',
        'kv' => 'kom',
        'ku' => 'kur',
        'lv' => 'lav',
        'mg' => 'mlg',
        'mn' => 'mon',
        'ms' => 'msa',
        'nb' => 'nob',
        'ne' => 'nep',
        'oj' => 'oji',
        'om' => 'orm',
        'or' => 'ori',
        'ps' => 'pus',
        'qu' => 'que',
        'ro' => 'ron',
        'sc' => 'srd',
        'sq' => 'sqi',
        'sw' => 'swa',
        'uz' => 'uzb',
        'yi' => 'yid',
        'za' => 'zha',
        'zh' => 'zho',
    );

    /**
     * @var LanguageBundleInterface
     */
    private $languageBundle;

    public function __construct(LanguageBundleInterface $languageBundle)
    {
        $this->languageBundle = $languageBundle;
    }

    /**
     * {@inheritdoc}
     */
    public function getBundleName()
    {
        return 'lang';
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(CompilationContext $context)
    {
        $tempDir = sys_get_temp_dir().'/icu-data-languages';

        // The language data is contained in the locales bundle in ICU <= 4.2
        if (IcuVersion::compare($context->getIcuVersion(), '4.2', '<=', 1)) {
            $sourceDir = $context->getSourceDir() . '/locales';
        } else {
            $sourceDir = $context->getSourceDir() . '/lang';
        }

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mkdir(array($tempDir, $tempDir.'/res'));
        $context->getFilesystem()->mirror($sourceDir, $tempDir.'/txt');

        $context->getCompiler()->compile($tempDir.'/txt', $tempDir.'/res');
        $context->getCompiler()->compile($context->getSourceDir().'/misc/metadata.txt', $tempDir.'/res');

        $reader = new BinaryBundleReader();
        $writer = new TextBundleWriter();

        // Collect supported locales of the bundle
        $availableLocales = $context->getLocaleScanner()->scanLocales($tempDir.'/txt');

        // Drop and regenerate txt files
        $context->getFilesystem()->remove($tempDir.'/txt');
        $context->getFilesystem()->mkdir($tempDir.'/txt');

        $languages = array();

        // Collect complete list of languages and scripts in all locales
        foreach ($availableLocales as $locale) {
            $bundle = $reader->read($tempDir.'/res', $locale);

            // isset() on \ResourceBundle returns true even if the value is null
            if (isset($bundle['Languages']) && null !== $bundle['Languages']) {
                $languageNames = iterator_to_array($bundle['Languages']);

                $writer->write($tempDir.'/txt', $locale, array(
                    'Version' => $bundle['Version'],
                    'Languages' => $languageNames,
                ));

                $languages = array_merge($languages, array_keys($languageNames));
            }
        }

        $languages = array_unique($languages);
        sort($languages);

        $root = $reader->read($tempDir.'/res', 'root');

        // Read the metadata bundle with the language aliases
        $metadata = $reader->read($tempDir.'/res', 'metadata');

        // Create the mapping from two-letter to three-letter codes
        $aliases = $metadata['languageAlias'];
        $alpha2ToAlpha3 = array();

        foreach ($aliases as $alias => $language) {
            if (2 === strlen($language) && 3 === strlen($alias)) {
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

                    if ($language !== $aliases[$alpha3]) {
                        throw new RuntimeException(
                            'The statically set three-letter mapping '.$alpha3.' '.
                            'for the language code '.$language.' seems to be '.
                            'an alias for '.$aliases[$alpha3].'. Wrong mapping?'
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

        // Create root file with all available locales
        $writer = new TextBundleWriter();
        $writer->write($tempDir.'/txt', 'root', array(
            'Version' => $root['Version'],
            'Languages' => array_combine($languages, $languages),
            'Aliases' => $metadata['languageAlias'],
            'Alpha2ToAlpha3' => $alpha2ToAlpha3,
        ));

        return $tempDir.'/txt';
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(CompilationContext $context)
    {
        // Remove the temporary directory
        $context->getFilesystem()->remove(sys_get_temp_dir().'/icu-data-languages');
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCreateStub(StubbingContext $context)
    {
        return array(
            'Languages' => $this->languageBundle->getLanguageNames('en'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContext $context)
    {
    }
}
