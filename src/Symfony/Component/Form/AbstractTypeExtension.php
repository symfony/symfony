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

use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractTypeExtension implements FormTypeExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $defaults = $this->getDefaultOptions(array());
        $allowedTypes = $this->getAllowedOptionValues(array());

        if (!empty($defaults)) {
            trigger_error('getDefaultOptions() is deprecated since version 2.1 and will be removed in 2.3. Use setDefaultOptions() instead.', E_USER_DEPRECATED);

            $resolver->setDefaults($defaults);
        }

        if (!empty($allowedTypes)) {
            trigger_error('getAllowedOptionValues() is deprecated since version 2.1 and will be removed in 2.3. Use setDefaultOptions() instead.', E_USER_DEPRECATED);

            $resolver->addAllowedValues($allowedTypes);
        }
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
