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
 * A form group bundling multiple forms in a hierarchical structure.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface FormInterface extends \ArrayAccess, \Traversable, \Countable
{
    /**
     * Sets the parent form.
     *
     * @param  FormInterface|null $parent The parent form or null if it's the root.
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadyBoundException If the form has already been bound.
     * @throws Exception\FormException         When trying to set a parent for a form with
     *                                         an empty name.
     */
    public function setParent(FormInterface $parent = null);

    /**
     * Returns the parent form.
     *
     * @return FormInterface|null The parent form or null if there is none.
     */
    public function getParent();

    /**
     * Adds a child to the form.
     *
     * @param  FormInterface $child The FormInterface to add as a child
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadyBoundException If the form has already been bound.
     * @throws Exception\FormException         When trying to add a child to a non-compound form.
     */
    public function add(FormInterface $child);

    /**
     * Returns the child with the given name.
     *
     * @param string $name The name of the child
     *
     * @return FormInterface The child form
     *
     * @throws \OutOfBoundsException If the named child does not exist.
     */
    public function get($name);

    /**
     * Returns whether a child with the given name exists.
     *
     * @param string $name The name of the child
     *
     * @return Boolean
     */
    public function has($name);

    /**
     * Removes a child from the form.
     *
     * @param  string $name The name of the child to remove
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadyBoundException If the form has already been bound.
     */
    public function remove($name);

    /**
     * Returns all children in this group.
     *
     * @return array An array of FormInterface instances
     */
    public function all();

    /**
     * Returns all errors.
     *
     * @return array An array of FormError instances that occurred during binding
     */
    public function getErrors();

    /**
     * Updates the form with default data.
     *
     * @param  mixed $modelData The data formatted as expected for the underlying object
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadyBoundException If the form has already been bound.
     * @throws Exception\FormException         If listeners try to call setData in a cycle. Or if
     *                                         the view data does not match the expected type
     *                                         according to {@link FormConfigInterface::getDataClass}.
     */
    public function setData($modelData);

    /**
     * Returns the data in the format needed for the underlying object.
     *
     * @return mixed
     */
    public function getData();

    /**
     * Returns the normalized data of the field.
     *
     * @return mixed When the field is not bound, the default data is returned.
     *               When the field is bound, the normalized bound data is
     *               returned if the field is valid, null otherwise.
     */
    public function getNormData();

    /**
     * Returns the data transformed by the value transformer.
     *
     * @return mixed
     */
    public function getViewData();

    /**
     * Returns the extra data.
     *
     * @return array The bound data which do not belong to a child
     */
    public function getExtraData();

    /**
     * Returns the form's configuration.
     *
     * @return FormConfigInterface The configuration.
     */
    public function getConfig();

    /**
     * Returns whether the field is bound.
     *
     * @return Boolean true if the form is bound to input values, false otherwise
     */
    public function isBound();

    /**
     * Returns the name by which the form is identified in forms.
     *
     * @return string The name of the form.
     */
    public function getName();

    /**
     * Returns the property path that the form is mapped to.
     *
     * @return Util\PropertyPathInterface The property path.
     */
    public function getPropertyPath();

    /**
     * Adds an error to this form.
     *
     * @param  FormError $error
     *
     * @return FormInterface The form instance
     */
    public function addError(FormError $error);

    /**
     * Returns whether the form and all children are valid.
     *
     * @return Boolean
     */
    public function isValid();

    /**
     * Returns whether the form is required to be filled out.
     *
     * If the form has a parent and the parent is not required, this method
     * will always return false. Otherwise the value set with setRequired()
     * is returned.
     *
     * @return Boolean
     */
    public function isRequired();

    /**
     * Returns whether this form is disabled.
     *
     * The content of a disabled form is displayed, but not allowed to be
     * modified. The validation of modified disabled forms should fail.
     *
     * Forms whose parents are disabled are considered disabled regardless of
     * their own state.
     *
     * @return Boolean
     */
    public function isDisabled();

    /**
     * Returns whether the form is empty.
     *
     * @return Boolean
     */
    public function isEmpty();

    /**
     * Returns whether the data in the different formats is synchronized.
     *
     * @return Boolean
     */
    public function isSynchronized();

    /**
     * Binds data to the form, transforms and validates it.
     *
     * @param  null|string|array $submittedData The data
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadyBoundException If the form has already been bound.
     */
    public function bind($submittedData);

    /**
     * Returns the root of the form tree.
     *
     * @return FormInterface The root of the tree
     */
    public function getRoot();

    /**
     * Returns whether the field is the root of the form tree.
     *
     * @return Boolean
     */
    public function isRoot();

    /**
     * Creates a view.
     *
     * @param FormView $parent The parent view
     *
     * @return FormView The view
     */
    public function createView(FormView $parent = null);
}
