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
 * The rule for compiling the language bundle.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.3.0
 */
class LanguageBundleTransformationRule implements TransformationRuleInterface
{
    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getBundleName()
    {
        return 'lang';
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function beforeCompile(CompilationContextInterface $context)
    {
        // The language data is contained in the locales bundle in ICU <= 4.2
        if (IcuVersion::compare($context->getIcuVersion(), '4.2', '<=', 1)) {
            return $context->getSourceDir() . '/locales';
        }

        return $context->getSourceDir() . '/lang';
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function afterCompile(CompilationContextInterface $context)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function beforeCreateStub(StubbingContextInterface $context)
    {
        return array(
            'Languages' => Intl::getLanguageBundle()->getLanguageNames('en'),
            'Scripts' => Intl::getLanguageBundle()->getScriptNames('en'),
        );
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function afterCreateStub(StubbingContextInterface $context)
    {
    }
}
