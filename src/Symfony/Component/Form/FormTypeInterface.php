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
    function buildForm(FormBuilder $builder, array $options);

    function buildView(FormView $view, FormInterface $form);

    function buildViewBottomUp(FormView $view, FormInterface $form);

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
     * Returns the name of the parent type.
     *
     * @param array $options
     *
     * @return string The name of the parent type
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