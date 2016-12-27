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
 * A wrapper for a form type and its extensions.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ResolvedFormTypeInterface
{
    /**
     * Returns the name of the type.
     *
     * @return string The type name
     */
    public function getName();

    /**
     * Returns the parent type.
     *
     * @return self|null The parent type or null
     */
    public function getParent();

    /**
     * Returns the wrapped form type.
     *
     * @return FormTypeInterface The wrapped form type
     */
    public function getInnerType();

    /**
     * Returns the extensions of the wrapped form type.
     *
     * @return FormTypeExtensionInterface[] An array of {@link FormTypeExtensionInterface} instances
     */
    public function getTypeExtensions();

    /**
     * Creates a new form builder for this type.
     *
     * @param FormFactoryInterface $factory The form factory
     * @param string               $name    The name for the builder
     * @param array                $options The builder options
     *
     * @return FormBuilderInterface The created form builder
     */
    public function createBuilder(FormFactoryInterface $factory, $name, array $options = array());

    /**
     * Creates a new form view for a form of this type.
     *
     * @param FormInterface $form   The form to create a view for
     * @param FormView      $parent The parent view or null
     *
     * @return FormView The created form view
     */
    public function createView(FormInterface $form, FormView $parent = null);

    /**
     * Configures a form builder for the type hierarchy.
     *
     * @param FormBuilderInterface $builder The builder to configure
     * @param array                $options The options used for the configuration
     */
    public function buildForm(FormBuilderInterface $builder, array $options);

    /**
     * Configures a form view for the type hierarchy.
     *
     * It is called before the children of the view are built.
     *
     * @param FormView      $view    The form view to configure
     * @param FormInterface $form    The form corresponding to the view
     * @param array         $options The options used for the configuration
     */
    public function buildView(FormView $view, FormInterface $form, array $options);

    /**
     * Finishes a form view for the type hierarchy.
     *
     * It is called after the children of the view have been built.
     *
     * @param FormView      $view    The form view to configure
     * @param FormInterface $form    The form corresponding to the view
     * @param array         $options The options used for the configuration
     */
    public function finishView(FormView $view, FormInterface $form, array $options);

    /**
     * Returns the configured options resolver used for this type.
     *
     * @return OptionsResolverInterface The options resolver
     */
    public function getOptionsResolver();
}
