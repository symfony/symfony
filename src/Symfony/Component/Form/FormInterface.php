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

use Symfony\Component\PropertyAccess\PropertyPathInterface;

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
     * @param FormInterface|null $parent The parent form or null if it's the root
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     * @throws Exception\LogicException            when trying to set a parent for a form with
     *                                             an empty name
     */
    public function setParent(self $parent = null);

    /**
     * Returns the parent form.
     *
     * @return self|null The parent form or null if there is none
     */
    public function getParent();

    /**
     * Adds or replaces a child to the form.
     *
     * @param FormInterface|string $child   The FormInterface instance or the name of the child
     * @param string|null          $type    The child's type, if a name was passed
     * @param array                $options The child's options, if a name was passed
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     * @throws Exception\LogicException            when trying to add a child to a non-compound form
     * @throws Exception\UnexpectedTypeException   if $child or $type has an unexpected type
     */
    public function add($child, $type = null, array $options = []);

    /**
     * Returns the child with the given name.
     *
     * @param string $name The name of the child
     *
     * @return self
     *
     * @throws Exception\OutOfBoundsException if the named child does not exist
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
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     */
    public function remove($name);

    /**
     * Returns all children in this group.
     *
     * @return self[]
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
     */
    public function getErrors($deep = false, $flatten = true);

    /**
     * Updates the form with default model data.
     *
     * @param mixed $modelData The data formatted as expected for the underlying object
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException     If the form has already been submitted
     * @throws Exception\LogicException                if the view data does not match the expected type
     *                                                 according to {@link FormConfigInterface::getDataClass}
     * @throws Exception\RuntimeException              If listeners try to call setData in a cycle or if
     *                                                 the form inherits data from its parent
     * @throws Exception\TransformationFailedException if the synchronization failed
     */
    public function setData($modelData);

    /**
     * Returns the model data in the format needed for the underlying object.
     *
     * @return mixed When the field is not submitted, the default data is returned.
     *               When the field is submitted, the default data has been bound
     *               to the submitted view data.
     *
     * @throws Exception\RuntimeException If the form inherits data but has no parent
     */
    public function getData();

    /**
     * Returns the normalized data of the field, used as internal bridge
     * between model data and view data.
     *
     * @return mixed When the field is not submitted, the default data is returned.
     *               When the field is submitted, the normalized submitted data
     *               is returned if the field is synchronized with the view data,
     *               null otherwise.
     *
     * @throws Exception\RuntimeException If the form inherits data but has no parent
     */
    public function getNormData();

    /**
     * Returns the view data of the field.
     *
     * It may be defined by {@link FormConfigInterface::getDataClass}.
     *
     * There are two cases:
     *
     * - When the form is compound the view data is mapped to the children.
     *   Each child will use its mapped data as model data.
     *   It can be an array, an object or null.
     *
     * - When the form is simple its view data is used to be bound
     *   to the submitted data.
     *   It can be a string or an array.
     *
     * In both cases the view data is the actual altered data on submission.
     *
     * @return mixed
     *
     * @throws Exception\RuntimeException If the form inherits data but has no parent
     */
    public function getViewData();

    /**
     * Returns the extra submitted data.
     *
     * @return array The submitted data which do not belong to a child
     */
    public function getExtraData();

    /**
     * Returns the form's configuration.
     *
     * @return FormConfigInterface The configuration instance
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
     * Only root forms are allowed to have an empty name.
     *
     * @return string The name of the form
     */
    public function getName();

    /**
     * Returns the property path that the form is mapped to.
     *
     * @return PropertyPathInterface|null The property path instance
     */
    public function getPropertyPath();

    /**
     * Adds an error to this form.
     *
     * @return $this
     */
    public function addError(FormError $error);

    /**
     * Returns whether the form and all children are valid.
     *
     * If the form is not submitted, this method always returns false (but will throw an exception in 4.0).
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
     * If the data is not synchronized, you can get the transformation failure
     * by calling {@link getTransformationFailure()}.
     *
     * If the form is not submitted, this method always returns true.
     *
     * @return bool
     */
    public function isSynchronized();

    /**
     * Returns the data transformation failure, if any, during submission.
     *
     * @return Exception\TransformationFailedException|null The transformation failure or null
     */
    public function getTransformationFailure();

    /**
     * Initializes the form tree.
     *
     * Should be called on the root form after constructing the tree.
     *
     * @return $this
     *
     * @throws Exception\RuntimeException If the form is not the root
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
     * @param mixed $request The request to handle
     *
     * @return $this
     */
    public function handleRequest($request = null);

    /**
     * Submits data to the form.
     *
     * @param string|array|null $submittedData The submitted data
     * @param bool              $clearMissing  Whether to set fields to NULL
     *                                         when they are missing in the
     *                                         submitted data. This argument
     *                                         is only used in compound form
     *
     * @return $this
     *
     * @throws Exception\AlreadySubmittedException if the form has already been submitted
     */
    public function submit($submittedData, $clearMissing = true);

    /**
     * Returns the root of the form tree.
     *
     * @return self The root of the tree, may be the instance itself
     */
    public function getRoot();

    /**
     * Returns whether the field is the root of the form tree.
     *
     * @return bool
     */
    public function isRoot();

    /**
     * @return FormView The view
     */
    public function createView(FormView $parent = null);
}
