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
use Symfony\Component\Intl\ResourceBundle\Transformer\CompilationContextInterface;
use Symfony\Component\Intl\ResourceBundle\Transformer\StubbingContextInterface;
use Symfony\Component\Intl\Util\IcuVersion;

/**
 * The rule for compiling the region bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class RegionBundleTransformationRule implements TransformationRuleInterface
{
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
            return $context->getSourceDir() . '/locales';
        }

        return $context->getSourceDir() . '/region';
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
            'Countries' => Intl::getRegionBundle()->getCountryNames('en'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function afterCreateStub(StubbingContextInterface $context)
    {
    }
}
