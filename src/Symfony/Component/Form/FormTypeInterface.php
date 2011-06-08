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

interface FormTypeInterface
{
    /**
     * Builds the form.
     *
     * This method gets called for each type in the hierarchy starting form the
     * top most type.
     * Type extensions can further modify the form.
     *
     * @see FormTypeExtensionInterface::buildForm()
     *
     * @param FormBuilder   $builder The form builder
     * @param array         $options The options
     */
    function buildForm(FormBuilder $builder, array $options);

    /**
     * Builds the form view.
     *
     * This method gets called for each type in the hierarchy starting form the
     * top most type.
     * Type extensions can further modify the view.
     *
     * @see FormTypeExtensionInterface::buildView()
     *
     * @param FormView      $view The view
     * @param FormInterface $form The form
     */
    function buildView(FormView $view, FormInterface $form);

    /**
     * Builds the form view.
     *
     * This method gets called for each type in the hierarchy starting form the
     * top most type.
     * Type extensions can further modify the view.
     *
     * Children views have been built when this method gets called so you get
     * a chance to modify them.
     *
     * @see FormTypeExtensionInterface::buildViewBottomUp()
     *
     * @param FormView      $view The view
     * @param FormInterface $form The form
     */
    function buildViewBottomUp(FormView $view, FormInterface $form);

    /**
     * Returns a builder for the current type.
     *
     * The builder is retrieved by going up in the type hierarchy when a type does
     * not provide one.
     *
     * @param string                $name       The name of the builder
     * @param FormFactoryInterface  $factory    The form factory
     * @param array                 $options    The options
     *
     * @return FormBuilder|null A form builder or null when the type does not have a builder
     */
    function createBuilder($name, FormFactoryInterface $factory, array $options);

    /**
     * Returns the default options for this type.
     *
     * @param array $options
     *
     * @return array The default options
     */
    function getDefaultOptions(array $options);

    /**
     * Returns the allowed option values for each option (if any).
     *
     * @param array $options
     *
     * @return array The allowed option values
     */
    function getAllowedOptionValues(array $options);

    /**
     * Returns the name of the parent type.
     *
     * @param array $options
     *
     * @return string|null The name of the parent type if any otherwise null
     */
    function getParent(array $options);

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    function getName();

    /**
     * Adds extensions for this type.
     *
     * @param array $extensions An array of FormTypeExtensionInterface
     *
     * @throws UnexpectedTypeException if any extension does not implement FormTypeExtensionInterface
     */
    function setExtensions(array $extensions);

    /**
     * Returns the extensions associated with this type.
     *
     * @return array An array of FormTypeExtensionInterface
     */
    function getExtensions();
}
