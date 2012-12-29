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
        set_error_handler(array('Symfony\Component\Form\Test\DeprecationErrorHandler', 'handleBC'));
        $resolver->setDefaults($this->getDefaultOptions());
        $resolver->addAllowedValues($this->getAllowedOptionValues());
        restore_error_handler();
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
        trigger_error('getDefaultOptions() is deprecated since version 2.1 and will be removed in 2.3. Use setDefaultOptions() instead.', E_USER_DEPRECATED);

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
        trigger_error('getAllowedOptionValues() is deprecated since version 2.1 and will be removed in 2.3. Use setDefaultOptions() instead.', E_USER_DEPRECATED);

        return array();
    }
}
