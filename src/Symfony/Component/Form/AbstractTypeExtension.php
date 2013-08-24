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
 *
 * @since v2.0.0
 */
abstract class AbstractTypeExtension implements FormTypeExtensionInterface
{
    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.1.0
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
    }
}
