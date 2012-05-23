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
interface FormTypeExtensionInterface
{
    /**
     * Builds the form.
     *
     * This method gets called after the extended type has built the form to
     * further modify it.
     *
     * @see FormTypeInterface::buildForm()
     *
     * @param FormBuilder $builder The form builder
     * @param array       $options The options
     */
    function buildForm(FormBuilder $builder, array $options);

    /**
     * Builds the view.
     *
     * This method gets called after the extended type has built the view to
     * further modify it.
     *
     * @see FormTypeInterface::buildView()
     *
     * @param FormView      $view    The view
     * @param FormInterface $form    The form
     * @param array         $options The options
     */
    function buildView(FormView $view, FormInterface $form, array $options);

    /**
     * Builds the view.
     *
     * This method gets called after the extended type has built the view to
     * further modify it.
     *
     * @see FormTypeInterface::buildViewBottomUp()
     *
     * @param FormView      $view    The view
     * @param FormInterface $form    The form
     * @param array         $options The options
     */
    function buildViewBottomUp(FormView $view, FormInterface $form, array $options);

    /**
     * Overrides the default options from the extended type.
     *
     * @param OptionsResolver $resolver The resolver for the options.
     */
    function setDefaultOptions(OptionsResolver $resolver);

    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    function getExtendedType();
}
