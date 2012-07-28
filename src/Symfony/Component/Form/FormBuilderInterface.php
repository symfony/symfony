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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormBuilderInterface extends \Traversable, \Countable, FormConfigBuilderInterface
{
    /**
     * Adds a new field to this group. A field must have a unique name within
     * the group. Otherwise the existing field is overwritten.
     *
     * If you add a nested group, this group should also be represented in the
     * object hierarchy.
     *
     * @param string|FormBuilderInterface $child
     * @param string|FormTypeInterface    $type
     * @param array                       $options
     *
     * @return FormBuilderInterface The builder object.
     */
    public function add($child, $type = null, array $options = array());

    /**
     * Creates a form builder.
     *
     * @param string                   $name    The name of the form or the name of the property
     * @param string|FormTypeInterface $type    The type of the form or null if name is a property
     * @param array                    $options The options
     *
     * @return FormBuilderInterface The created builder.
     */
    public function create($name, $type = null, array $options = array());

    /**
     * Returns a child by name.
     *
     * @param string $name The name of the child
     *
     * @return FormBuilderInterface The builder for the child
     *
     * @throws Exception\FormException if the given child does not exist
     */
    public function get($name);
    /**
     * Removes the field with the given name.
     *
     * @param string $name
     *
     * @return FormBuilderInterface The builder object.
     */
    public function remove($name);

    /**
     * Returns whether a field with the given name exists.
     *
     * @param string $name
     *
     * @return Boolean
     */
    public function has($name);

    /**
     * Returns the children.
     *
     * @return array
     */
    public function all();

    /**
     * Returns the associated form factory.
     *
     * @return FormFactoryInterface The factory
     */
    public function getFormFactory();

    /**
     * Creates the form.
     *
     * @return Form The form
     */
    public function getForm();

    /**
     * Sets the parent builder.
     *
     * @param FormBuilderInterface $parent The parent builder
     *
     * @return FormBuilderInterface The builder object.
     */
    public function setParent(FormBuilderInterface $parent = null);

    /**
     * Returns the parent builder.
     *
     * @return FormBuilderInterface The parent builder
     */
    public function getParent();

    /**
     * Returns whether the builder has a parent.
     *
     * @return Boolean
     */
    public function hasParent();
}
