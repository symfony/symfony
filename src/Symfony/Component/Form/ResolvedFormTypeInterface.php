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
     * @return string
     */
    public function getBlockPrefix();

    /**
     * Returns the parent type.
     *
     * @return self|null
     */
    public function getParent();

    /**
     * Returns the wrapped form type.
     *
     * @return FormTypeInterface
     */
    public function getInnerType();

    /**
     * Returns the extensions of the wrapped form type.
     *
     * @return FormTypeExtensionInterface[]
     */
    public function getTypeExtensions();

    /**
     * Creates a new form builder for this type.
     *
     * @param string $name The name for the builder
     *
     * @return FormBuilderInterface
     */
    public function createBuilder(FormFactoryInterface $factory, string $name, array $options = []);

    /**
     * Creates a new form view for a form of this type.
     *
     * @return FormView
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
     * @return OptionsResolver
     */
    public function getOptionsResolver();
}
