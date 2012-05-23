<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractTypeExtension implements FormTypeExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults($this->getDefaultOptions());
        $resolver->addAllowedValues($this->getAllowedOptionValues());
    }

    /**
     * Overrides the default options form the extended type.
     *
     * @return array
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     *             Use {@link setDefaultOptions()} instead.
     */
    public function getDefaultOptions()
    {
        return array();
    }

    /**
     * Returns the allowed option values for each option (if any).
     *
     * @return array The allowed option values
     *
     * @deprecated Deprecated since version 2.1, to be removed in 2.3.
     *             Use {@link setDefaultOptions()} instead.
     */
    public function getAllowedOptionValues()
    {
        return array();
    }
}
