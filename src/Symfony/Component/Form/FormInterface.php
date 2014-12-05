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
     * @param FormInterface|null $parent The parent form or null if it's the root.
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadySubmittedException If the form has already been submitted.
     * @throws Exception\LogicException            When trying to set a parent for a form with
     *                                             an empty name.
     */
    public function setParent(FormInterface $parent = null);

    /**
     * Returns the parent form.
     *
     * @return FormInterface|null The parent form or null if there is none.
     */
    public function getParent();

    /**
     * Adds or replaces a child to the form.
     *
     * @param FormInterface|string|int $child   The FormInterface instance or the name of the child.
     * @param string|null              $type    The child's type, if a name was passed.
     * @param array                    $options The child's options, if a name was passed.
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadySubmittedException If the form has already been submitted.
     * @throws Exception\LogicException            When trying to add a child to a non-compound form.
     * @throws Exception\UnexpectedTypeException   If $child or $type has an unexpected type.
     */
    public function add($child, $type = null, array $options = array());

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
     * @return bool
     */
    public function has($name);

    /**
     * Removes a child from the form.
     *
     * @param string $name The name of the child to remove
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadySubmittedException If the form has already been submitted.
     */
    public function remove($name);

    /**
     * Returns all children in this group.
     *
     * @return FormInterface[] An array of FormInterface instances
     */
    public function all();

    /**
     * Returns the errors of this form.
     *
     * @param bool $deep    Whether to include errors of child forms as well
     * @param bool $flatten Whether to flatten the list of errors in case
     *                      $deep is set to true
     *
     * @return FormErrorIterator An iterator over the {@link FormError}
     *                           instances that where added to this form
     *
     * @since 2.5 Since version 2.5 this method returns a
     *            {@link FormErrorIterator} instance instead of an array
     */
    public function getErrors($deep = false, $flatten = true);

    /**
     * Updates the form with default data.
     *
     * @param mixed $modelData The data formatted as expected for the underlying object
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadySubmittedException If the form has already been submitted.
     * @throws Exception\LogicException            If listeners try to call setData in a cycle. Or if
     *                                             the view data does not match the expected type
     *                                             according to {@link FormConfigInterface::getDataClass}.
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
     * @return mixed When the field is not submitted, the default data is returned.
     *               When the field is submitted, the normalized submitted data is
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
     * @return array The submitted data which do not belong to a child
     */
    public function getExtraData();

    /**
     * Returns the form's configuration.
     *
     * @return FormConfigInterface The configuration.
     */
    public function getConfig();

    /**
     * Returns whether the form is submitted.
     *
     * @return bool true if the form is submitted, false otherwise
     */
    public function isSubmitted();

    /**
     * Returns the name by which the form is identified in forms.
     *
     * @return string The name of the form.
     */
    public function getName();

    /**
     * Returns the property path that the form is mapped to.
     *
     * @return \Symfony\Component\PropertyAccess\PropertyPathInterface The property path.
     */
    public function getPropertyPath();

    /**
     * Adds an error to this form.
     *
     * @param FormError $error
     *
     * @return FormInterface The form instance
     */
    public function addError(FormError $error);

    /**
     * Returns whether the form and all children are valid.
     *
     * If the form is not submitted, this method always returns false.
     *
     * @return bool
     */
    public function isValid();

    /**
     * Returns whether the form is required to be filled out.
     *
     * If the form has a parent and the parent is not required, this method
     * will always return false. Otherwise the value set with setRequired()
     * is returned.
     *
     * @return bool
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
     * @return bool
     */
    public function isDisabled();

    /**
     * Returns whether the form is empty.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Returns whether the data in the different formats is synchronized.
     *
     * @return bool
     */
    public function isSynchronized();

    /**
     * Initializes the form tree.
     *
     * Should be called on the root form after constructing the tree.
     *
     * @return FormInterface The form instance.
     */
    public function initialize();

    /**
     * Inspects the given request and calls {@link submit()} if the form was
     * submitted.
     *
     * Internally, the request is forwarded to the configured
     * {@link RequestHandlerInterface} instance, which determines whether to
     * submit the form or not.
     *
     * @param mixed $request The request to handle.
     *
     * @return FormInterface The form instance.
     */
    public function handleRequest($request = null);

    /**
     * Submits data to the form, transforms and validates it.
     *
     * @param null|string|array $submittedData The submitted data.
     * @param bool              $clearMissing  Whether to set fields to NULL
     *                                         when they are missing in the
     *                                         submitted data.
     *
     * @return FormInterface The form instance
     *
     * @throws Exception\AlreadySubmittedException If the form has already been submitted.
     */
    public function submit($submittedData, $clearMissing = true);

    /**
     * Returns the root of the form tree.
     *
     * @return FormInterface The root of the tree
     */
    public function getRoot();

    /**
     * Returns whether the field is the root of the form tree.
     *
     * @return bool
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
