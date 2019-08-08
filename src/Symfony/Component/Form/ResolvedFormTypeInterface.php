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
 * A wrapper for a form type and its extensions.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface ResolvedFormTypeInterface
{
    /**
     * Returns the prefix of the template block name for this type.
     *
     * @return string The prefix of the template block name
     */
    public function getBlockPrefix();

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
     * @param string $name The name for the builder
     *
     * @return FormBuilderInterface The created form builder
     */
    public function createBuilder(FormFactoryInterface $factory, $name, array $options = []);

    /**
     * Creates a new form view for a form of this type.
     *
     * @return FormView The created form view
     */
    public function createView(FormInterface $form, FormView $parent = null);

    /**
     * Configures a form builder for the type hierarchy.
     */
    public function buildForm(FormBuilderInterface $builder, array $options);

    /**
     * Configures a form view for the type hierarchy.
     *
     * It is called before the children of the view are built.
     */
    public function buildView(FormView $view, FormInterface $form, array $options);

    /**
     * Finishes a form view for the type hierarchy.
     *
     * It is called after the children of the view have been built.
     */
    public function finishView(FormView $view, FormInterface $form, array $options);

    /**
     * Returns the configured options resolver used for this type.
     *
     * @return OptionsResolver The options resolver
     */
    public function getOptionsResolver();
}
