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

        $meta = array(
            'AvailableLocales' => $context->getLocaleScanner()->scanLocales($tempDir.'/txt'),
            'Languages' => array(),
            'Scripts' => array(),
        );

        $reader = new BinaryBundleReader();

        // Collect complete list of languages and scripts in all locales
        foreach ($meta['AvailableLocales'] as $locale) {
            $bundle = $reader->read($tempDir.'/res', $locale);

            // isset() on \ResourceBundle returns true even if the value is null
            if (isset($bundle['Languages']) && null !== $bundle['Languages']) {
                $meta['Languages'] = array_merge(
                    $meta['Languages'],
                    array_keys(iterator_to_array($bundle['Languages']))
                );
            }

            if (isset($bundle['Scripts']) && null !== $bundle['Scripts']) {
                $meta['Scripts'] = array_merge(
                    $meta['Scripts'],
                    array_keys(iterator_to_array($bundle['Scripts']))
                );
            }
        }

        $meta['Languages'] = array_unique($meta['Languages']);
        sort($meta['Languages']);

        $meta['Scripts'] = array_unique($meta['Scripts']);
        sort($meta['Scripts']);

        // Create meta file with all available locales
        $writer = new TextBundleWriter();
        $writer->write($tempDir.'/txt', 'meta', $meta, false);

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
            'Scripts' => $this->languageBundle->getScriptNames('en'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContext $context)
    {
    }
}
