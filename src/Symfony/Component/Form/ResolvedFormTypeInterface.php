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
     * @return string The type name.
     */
    public function getName();

    /**
     * Returns the parent type.
     *
     * @return ResolvedFormTypeInterface The parent type or null.
     */
    public function getParent();

    /**
     * Returns the wrapped form type.
     *
     * @return FormTypeInterface The wrapped form type.
     */
    public function getInnerType();

    /**
     * Returns the extensions of the wrapped form type.
     *
     * @return array An array of {@link FormTypeExtensionInterface} instances.
     */
    public function getTypeExtensions();

    /**
     * Creates a new form builder for this type.
     *
     * @param FormFactoryInterface $factory The form factory.
     * @param string               $name    The name for the builder.
     * @param array                $options The builder options.
     * @param FormBuilderInterface $parent  The parent builder object or null.
     *
     * @return FormBuilderInterface The created form builder.
     */
    public function createBuilder(FormFactoryInterface $factory, $name, array $options = array(), FormBuilderInterface $parent = null);

    /**
     * Creates a new form view for a form of this type.
     *
     * @param FormInterface     $form   The form to create a view for.
     * @param FormView $parent The parent view or null.
     *
     * @return FormView The created form view.
     */
    public function createView(FormInterface $form, FormView $parent = null);
}
