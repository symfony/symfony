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
     */
    public function getBlockPrefix(): string;

    /**
     * Returns the parent type.
     */
    public function getParent(): ?self;

    /**
     * Returns the wrapped form type.
     */
    public function getInnerType(): FormTypeInterface;

    /**
     * Returns the extensions of the wrapped form type.
     *
     * @return FormTypeExtensionInterface[]
     */
    public function getTypeExtensions(): array;

    /**
     * Creates a new form builder for this type.
     *
     * @param string $name The name for the builder
     */
    public function createBuilder(FormFactoryInterface $factory, string $name, array $options = []): FormBuilderInterface;

    /**
     * Creates a new form view for a form of this type.
     */
    public function createView(FormInterface $form, ?FormView $parent = null): FormView;

    /**
     * Configures a form builder for the type hierarchy.
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void;

    /**
     * Configures a form view for the type hierarchy.
     *
     * It is called before the children of the view are built.
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void;

    /**
     * Finishes a form view for the type hierarchy.
     *
     * It is called after the children of the view have been built.
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void;

    /**
     * Returns the configured options resolver used for this type.
     */
    public function getOptionsResolver(): OptionsResolver;
}
