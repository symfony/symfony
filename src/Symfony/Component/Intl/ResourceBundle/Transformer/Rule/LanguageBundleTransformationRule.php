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

use Symfony\Component\Intl\Intl;
use Symfony\Component\Intl\ResourceBundle\LanguageBundleInterface;
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContextInterface;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContextInterface;
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
    public function beforeCompile(CompilationContextInterface $context)
    {
        $tempDir = sys_get_temp_dir().'/icu-data-languages';

        // The language data is contained in the locales bundle in ICU <= 4.2
        if (IcuVersion::compare($context->getIcuVersion(), '4.2', '<=', 1)) {
            $sourceDir = $context->getSourceDir() . '/locales';
        } else {
            $sourceDir = $context->getSourceDir() . '/lang';
        }

        $context->getFilesystem()->remove($tempDir);
        $context->getFilesystem()->mirror($sourceDir, $tempDir);

        // Create misc file with all available locales
        $writer = new TextBundleWriter();
        $writer->write($tempDir, 'misc', array(
            'Locales' => $context->getLocaleScanner()->scanLocales($tempDir),
        ), false);

        return $tempDir;
    }

    /**
     * {@inheritdoc}
     */
    public function afterCompile(CompilationContextInterface $context)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCreateStub(StubbingContextInterface $context)
    {
        return array(
            'Languages' => $this->languageBundle->getLanguageNames('en'),
            'Scripts' => $this->languageBundle->getScriptNames('en'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContextInterface $context)
    {
    }
}
