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

<<<<<<< HEAD
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
=======
use Symfony\Component\OptionsResolver\OptionsResolver;
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormTypeExtensionInterface
{
    /**
     * Builds the form.
     *
     * This method is called after the extended type has built the form to
     * further modify it.
     *
     * @see FormTypeInterface::buildForm()
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array                $options The options
     */
    public function buildForm(FormBuilderInterface $builder, array $options);

    /**
     * Builds the view.
     *
     * This method is called after the extended type has built the view to
     * further modify it.
     *
     * @see FormTypeInterface::buildView()
     *
     * @param FormView      $view    The view
     * @param FormInterface $form    The form
     * @param array         $options The options
     */
    public function buildView(FormView $view, FormInterface $form, array $options);

    /**
     * Finishes the view.
     *
     * This method is called after the extended type has finished the view to
     * further modify it.
     *
     * @see FormTypeInterface::finishView()
     *
     * @param FormView      $view    The view
     * @param FormInterface $form    The form
     * @param array         $options The options
     */
    public function finishView(FormView $view, FormInterface $form, array $options);

    /**
<<<<<<< HEAD
     * Overrides the default options from the extended type.
     *
     * @param OptionsResolverInterface $resolver The resolver for the options.
     *
     * @deprecated Deprecated since Symfony 2.7, to be removed in Symfony 3.0.
     *             Use the method configureOptions instead. This method will be
     *             added to the FormTypeExtensionInterface with Symfony 3.0
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver);
=======
     * Configures the options for this type.
     *
     * @param OptionsResolver $resolver The resolver for the options.
     *
     */
    public function configureOptions(OptionsResolver $resolver);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

    /**
     * Returns the name of the type being extended.
     *
     * @return string The name of the type being extended
     */
    public function getExtendedType();
}
