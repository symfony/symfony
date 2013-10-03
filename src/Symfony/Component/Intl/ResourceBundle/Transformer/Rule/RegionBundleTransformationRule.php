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
use Symfony\Component\Intl\ResourceBundle\RegionBundleInterface;
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContextInterface;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContextInterface;
use Symfony\Component\Intl\ResourceBundle\Writer\TextBundleWriter;
use Symfony\Component\Intl\Util\IcuVersion;

/**
 * The rule for compiling the region bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RegionBundleTransformationRule implements TransformationRuleInterface
{
    /**
     * @var RegionBundleInterface
     */
    private $regionBundle;

    public function __construct(RegionBundleInterface $regionBundle)
    {
        $this->regionBundle = $regionBundle;
    }

    /**
     * {@inheritdoc}
     */
    public function getBundleName()
    {
        return 'region';
    }

    /**
     * {@inheritdoc}
     */
    public function beforeCompile(CompilationContextInterface $context)
    {
        // The region data is contained in the locales bundle in ICU <= 4.2
        if (IcuVersion::compare($context->getIcuVersion(), '4.2', '<=', 1)) {
            $sourceDir = $context->getSourceDir() . '/locales';
        } else {
            $sourceDir = $context->getSourceDir() . '/region';
        }

        // Create misc file with all available locales
        $writer = new TextBundleWriter();
        $writer->write($sourceDir, 'misc', array(
            'Locales' => $context->getLocaleScanner()->scanLocales($sourceDir),
        ), false);

        return $sourceDir;
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
            'Countries' => $this->regionBundle->getCountryNames('en'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContextInterface $context)
    {
    }
}
