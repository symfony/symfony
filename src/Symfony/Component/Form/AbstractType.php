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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

abstract class AbstractType implements FormTypeInterface
{
    /**
     * The extensions for this type
     * @var array An array of FormTypeExtensionInterface instances
     */
    private $extensions = array();

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
    public function buildForm(FormBuilder $builder, array $options)
    {
    }

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
    public function buildView(FormView $view, FormInterface $form)
    {
    }

    /**
     * Builds the form view.
     *
     * This method gets called for each type in the hierarchy starting form the
     * top most type.
     * Type extensions can further modify the view.
     *
     * Children views have been built while this method gets called so you get
     * a chance to modify them.
     *
     * @see FormTypeExtensionInterface::buildViewBottomUp()
     *
     * @param FormView      $view The view
     * @param FormInterface $form The form
     */
    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
    }

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
    public function createBuilder($name, FormFactoryInterface $factory, array $options)
    {
        return null;
    }

    /**
     * Returns the default options for this type.
     *
     * @param array $options
     *
     * @return array The default options
     */
    public function getDefaultOptions(array $options)
    {
        return array();
    }

    /**
     * Returns the allowed option values for each option (if any).
     *
     * @param array $options
     *
     * @return array The allowed option values
     */
    public function getAllowedOptionValues(array $options)
    {
        return array();
    }

    /**
     * Returns the name of the parent type.
     *
     * @param array $options
     *
     * @return string|null The name of the parent type if any otherwise null
     */
    public function getParent(array $options)
    {
        return 'form';
    }

    /**
     * Adds extensions for this type.
     *
     * @param array $extensions An array of FormTypeExtensionInterface
     *
     * @throws UnexpectedTypeException if any extension does not implement FormTypeExtensionInterface
     */
    public function setExtensions(array $extensions)
    {
        foreach ($extensions as $extension) {
            if (!$extension instanceof FormTypeExtensionInterface) {
                throw new UnexpectedTypeException($extension, 'Symfony\Component\Form\FormTypeExtensionInterface');
            }
        }

        $this->extensions = $extensions;
    }

    /**
     * Returns the extensions associated with this type.
     *
     * @return array An array of FormTypeExtensionInterface
     */
    public function getExtensions()
    {
        return $this->extensions;
    }
}
