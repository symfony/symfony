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
interface FormTypeInterface
{
    /**
     * Builds the form.
     *
     * This method is called for each type in the hierarchy starting form the
     * top most type. Type extensions can further modify the form.
     *
     * @see FormTypeExtensionInterface::buildForm()
     *
     * @param FormBuilderInterface $builder The form builder
     * @param array                $options The options
     */
    function buildForm(FormBuilderInterface $builder, array $options);

    /**
     * Builds the form view.
     *
     * This method is called for each type in the hierarchy starting form the
     * top most type. Type extensions can further modify the view.
     *
     * A view of a form is built before the views of the child forms are built.
     * This means that you cannot access child views in this method. If you need
     * to do so, move your logic to {@link finishView()} instead.
     *
     * @see FormTypeExtensionInterface::buildView()
     *
     * @param FormViewInterface $view    The view
     * @param FormInterface     $form    The form
     * @param array             $options The options
     */
    function buildView(FormViewInterface $view, FormInterface $form, array $options);

    /**
     * Finishes the form view.
     *
     * This method gets called for each type in the hierarchy starting form the
     * top most type. Type extensions can further modify the view.
     *
     * When this method is called, views of the form's children have already
     * been built and finished and can be accessed. You should only implement
     * such logic in this method that actually accesses child views. For everything
     * else you are recommended to implement {@link buildView()} instead.
     *
     * @see FormTypeExtensionInterface::finishView()
     *
     * @param FormViewInterface $view    The view
     * @param FormInterface     $form    The form
     * @param array             $options The options
     */
    function finishView(FormViewInterface $view, FormInterface $form, array $options);

    /**
     * Returns a builder for the current type.
     *
     * The builder is retrieved by going up in the type hierarchy when a type does
     * not provide one.
     *
     * @param string               $name    The name of the builder
     * @param FormFactoryInterface $factory The form factory
     * @param array                $options The options
     *
     * @return FormBuilderInterface|null A form builder or null when the type does not have a builder
     */
    function createBuilder($name, FormFactoryInterface $factory, array $options);

    /**
     * Sets the default options for this type.
     *
     * @param OptionsResolverInterface $resolver The resolver for the options.
     */
    function setDefaultOptions(OptionsResolverInterface $resolver);

    /**
     * Returns the name of the parent type.
     *
     * @return string|null The name of the parent type if any, null otherwise.
     */
    function getParent();

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    function getName();

    /**
     * Sets the extensions for this type.
     *
     * @param array $extensions An array of FormTypeExtensionInterface
     *
     * @throws Exception\UnexpectedTypeException if any extension does not implement FormTypeExtensionInterface
     */
    function setExtensions(array $extensions);

    /**
     * Returns the extensions associated with this type.
     *
     * @return array An array of FormTypeExtensionInterface
     */
    function getExtensions();
}
