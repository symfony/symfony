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

interface FormFactoryInterface
{
    /**
     * Returns a form.
     *
     * @see createBuilder()
     *
     * @param string|FormTypeInterface  $type       The type of the form
     * @param mixed                     $data       The initial data
     * @param array                     $options    The options
     *
     * @return Form The form named after the type
     *
     * @throws FormException if any given option is not applicable to the given type
     */
    function create($type, $data = null, array $options = array());

    /**
     * Returns a form.
     *
     * @param string|FormTypeInterface  $type       The type of the form
     * @param string                    $name       The name of the form
     * @param mixed                     $data       The initial data
     * @param array                     $options    The options
     *
     * @return Form The form
     *
     * @throws FormException if any given option is not applicable to the given type
     */
    function createNamed($type, $name, $data = null, array $options = array());

    /**
     * Returns a form for a property of a class.
     *
     * @param string $class     The fully qualified class name
     * @param string $property  The name of the property to guess for
     * @param mixed  $data      The initial data
     * @param array  $options   The options for the builder
     *
     * @return Form The form named after the property
     *
     * @throws FormException if any given option is not applicable to the form type
     */
    function createForProperty($class, $property, $data = null, array $options = array());

    /**
     * Returns a form builder
     *
     * @param string|FormTypeInterface  $type       The type of the form
     * @param mixed                     $data       The initial data
     * @param array                     $options    The options
     *
     * @return FormBuilder The form builder
     *
     * @throws FormException if any given option is not applicable to the given type
     */
    function createBuilder($type, $data = null, array $options = array());

    /**
     * Returns a form builder.
     *
     * @param string|FormTypeInterface  $type       The type of the form
     * @param string                    $name       The name of the form
     * @param mixed                     $data       The initial data
     * @param array                     $options    The options
     *
     * @return FormBuilder The form builder
     *
     * @throws FormException if any given option is not applicable to the given type
     */
    function createNamedBuilder($type, $name, $data = null, array $options = array());

    /**
     * Returns a form builder for a property of a class.
     *
     * If any of the 'max_length', 'required' and type options can be guessed,
     * and are not provided in the options argument, the guessed value is used.
     *
     * @param string $class     The fully qualified class name
     * @param string $property  The name of the property to guess for
     * @param mixed  $data      The initial data
     * @param array  $options   The options for the builder
     *
     * @return FormBuilder The form builder named after the property
     *
     * @throws FormException if any given option is not applicable to the form type
     */
    function createBuilderForProperty($class, $property, $data = null, array $options = array());

    function getType($name);

    function hasType($name);

    function addType(FormTypeInterface $type);
}
