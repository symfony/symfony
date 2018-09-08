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

use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Exception\OutOfBoundsException;
use Symfony\Component\Form\Exception\RuntimeException;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Util\FormUtil;
use Symfony\Component\Form\Util\InheritDataAwareIterator;
use Symfony\Component\Form\Util\OrderedHashMap;
use Symfony\Component\PropertyAccess\PropertyPath;

/**
 * Form represents a form.
 *
 * To implement your own form fields, you need to have a thorough understanding
 * of the data flow within a form. A form stores its data in three different
 * representations:
 *
 *   (1) the "model" format required by the form's object
 *   (2) the "normalized" format for internal processing
 *   (3) the "view" format used for display
 *
 * A date field, for example, may store a date as "Y-m-d" string (1) in the
 * object. To facilitate processing in the field, this value is normalized
 * to a DateTime object (2). In the HTML representation of your form, a
 * localized string (3) is presented to and modified by the user.
 *
 * In most cases, format (1) and format (2) will be the same. For example,
 * a checkbox field uses a Boolean value for both internal processing and
 * storage in the object. In these cases you simply need to set a value
 * transformer to convert between formats (2) and (3). You can do this by
 * calling addViewTransformer().
 *
 * In some cases though it makes sense to make format (1) configurable. To
 * demonstrate this, let's extend our above date field to store the value
 * either as "Y-m-d" string or as timestamp. Internally we still want to
 * use a DateTime object for processing. To convert the data from string/integer
 * to DateTime you can set a normalization transformer by calling
 * addModelTransformer(). The normalized data is then converted to the displayed
 * data as described before.
 *
 * The conversions (1) -> (2) -> (3) use the transform methods of the transformers.
 * The conversions (3) -> (2) -> (1) use the reverseTransform methods of the transformers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form implements \IteratorAggregate, FormInterface
{
    /**
     * The form's configuration.
     *
     * @var FormConfigInterface
     */
    private $config;

    /**
     * The parent of this form.
     *
     * @var FormInterface
     */
    private $parent;

    /**
     * The children of this form.
     *
     * @var FormInterface[] A map of FormInterface instances
     */
    private $children;

    /**
     * The errors of this form.
     *
     * @var FormError[] An array of FormError instances
     */
    private $errors = array();

    /**
     * Whether this form was submitted.
     *
     * @var bool
     */
    private $submitted = false;

    /**
     * The button that was used to submit the form.
     *
     * @var Button
     */
    private $clickedButton;

    /**
     * The form data in model format.
     *
     * @var mixed
     */
    private $modelData;

    /**
     * The form data in normalized format.
     *
     * @var mixed
     */
    private $normData;

    /**
     * The form data in view format.
     *
     * @var mixed
     */
    private $viewData;

    /**
     * The submitted values that don't belong to any children.
     *
     * @var array
     */
    private $extraData = array();

    /**
     * Returns the transformation failure generated during submission, if any.
     *
     * @var TransformationFailedException|null
     */
    private $transformationFailure;

    /**
     * Whether the form's data has been initialized.
     *
     * When the data is initialized with its default value, that default value
     * is passed through the transformer chain in order to synchronize the
     * model, normalized and view format for the first time. This is done
     * lazily in order to save performance when {@link setData()} is called
     * manually, making the initialization with the configured default value
     * superfluous.
     *
     * @var bool
     */
    private $defaultDataSet = false;

    /**
     * Whether setData() is currently being called.
     *
     * @var bool
     */
    private $lockSetData = false;

    /**
     * Creates a new form based on the given configuration.
     *
     * @throws LogicException if a data mapper is not provided for a compound form
     */
    public function __construct(FormConfigInterface $config)
    {
        // Compound forms always need a data mapper, otherwise calls to
        // `setData` and `add` will not lead to the correct population of
        // the child forms.
        if ($config->getCompound() && !$config->getDataMapper()) {
            throw new LogicException('Compound forms need a data mapper');
        }

        // If the form inherits the data from its parent, it is not necessary
        // to call setData() with the default data.
        if ($config->getInheritData()) {
            $this->defaultDataSet = true;
        }

        $this->config = $config;
        $this->children = new OrderedHashMap();
    }

    public function __clone()
    {
        $this->children = clone $this->children;

        foreach ($this->children as $key => $child) {
            $this->children[$key] = clone $child;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->config->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getPropertyPath()
    {
        if (null !== $this->config->getPropertyPath()) {
            return $this->config->getPropertyPath();
        }

        if (null === $this->getName() || '' === $this->getName()) {
            return;
        }

        $parent = $this->parent;

        while ($parent && $parent->getConfig()->getInheritData()) {
            $parent = $parent->getParent();
        }

        if ($parent && null === $parent->getConfig()->getDataClass()) {
            return new PropertyPath('['.$this->getName().']');
        }

        return new PropertyPath($this->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function isRequired()
    {
        if (null === $this->parent || $this->parent->isRequired()) {
            return $this->config->getRequired();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isDisabled()
    {
        if (null === $this->parent || !$this->parent->isDisabled()) {
            return $this->config->getDisabled();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function setParent(FormInterface $parent = null)
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('You cannot set the parent of a submitted form');
        }

        if (null !== $parent && '' === $this->config->getName()) {
            throw new LogicException('A form with an empty name cannot have a parent form.');
        }

        $this->parent = $parent;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot()
    {
        return $this->parent ? $this->parent->getRoot() : $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isRoot()
    {
        return null === $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function setData($modelData)
    {
        // If the form is submitted while disabled, it is set to submitted, but the data is not
        // changed. In such cases (i.e. when the form is not initialized yet) don't
        // abort this method.
        if ($this->submitted && $this->defaultDataSet) {
            throw new AlreadySubmittedException('You cannot change the data of a submitted form.');
        }

        // If the form inherits its parent's data, disallow data setting to
        // prevent merge conflicts
        if ($this->config->getInheritData()) {
            throw new RuntimeException('You cannot change the data of a form inheriting its parent data.');
        }

        // Don't allow modifications of the configured data if the data is locked
        if ($this->config->getDataLocked() && $modelData !== $this->config->getData()) {
            return $this;
        }

        if (\is_object($modelData) && !$this->config->getByReference()) {
            $modelData = clone $modelData;
        }

        if ($this->lockSetData) {
            throw new RuntimeException('A cycle was detected. Listeners to the PRE_SET_DATA event must not call setData(). You should call setData() on the FormEvent object instead.');
        }

        $this->lockSetData = true;
        $dispatcher = $this->config->getEventDispatcher();

        // Hook to change content of the data
        if ($dispatcher->hasListeners(FormEvents::PRE_SET_DATA)) {
            $event = new FormEvent($this, $modelData);
            $dispatcher->dispatch(FormEvents::PRE_SET_DATA, $event);
            $modelData = $event->getData();
        }

        // Treat data as strings unless a value transformer exists
        if (!$this->config->getViewTransformers() && !$this->config->getModelTransformers() && is_scalar($modelData)) {
            $modelData = (string) $modelData;
        }

        // Synchronize representations - must not change the content!
        $normData = $this->modelToNorm($modelData);
        $viewData = $this->normToView($normData);

        // Validate if view data matches data class (unless empty)
        if (!FormUtil::isEmpty($viewData)) {
            $dataClass = $this->config->getDataClass();

            if (null !== $dataClass && !$viewData instanceof $dataClass) {
                $actualType = \is_object($viewData)
                    ? 'an instance of class '.\get_class($viewData)
                    : 'a(n) '.\gettype($viewData);

                throw new LogicException('The form\'s view data is expected to be an instance of class '.$dataClass.', but is '.$actualType.'. You can avoid this error by setting the "data_class" option to null or by adding a view transformer that transforms '.$actualType.' to an instance of '.$dataClass.'.');
            }
        }

        $this->modelData = $modelData;
        $this->normData = $normData;
        $this->viewData = $viewData;
        $this->defaultDataSet = true;
        $this->lockSetData = false;

        // It is not necessary to invoke this method if the form doesn't have children,
        // even if the form is compound.
        if (\count($this->children) > 0) {
            // Update child forms from the data
            $iterator = new InheritDataAwareIterator($this->children);
            $iterator = new \RecursiveIteratorIterator($iterator);
            $this->config->getDataMapper()->mapDataToForms($viewData, $iterator);
        }

        if ($dispatcher->hasListeners(FormEvents::POST_SET_DATA)) {
            $event = new FormEvent($this, $modelData);
            $dispatcher->dispatch(FormEvents::POST_SET_DATA, $event);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        if ($this->config->getInheritData()) {
            if (!$this->parent) {
                throw new RuntimeException('The form is configured to inherit its parent\'s data, but does not have a parent.');
            }

            return $this->parent->getData();
        }

        if (!$this->defaultDataSet) {
            if ($this->lockSetData) {
                throw new RuntimeException('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getData() if the form data has not already been set. You should call getData() on the FormEvent object instead.');
            }

            $this->setData($this->config->getData());
        }

        return $this->modelData;
    }

    /**
     * {@inheritdoc}
     */
    public function getNormData()
    {
        if ($this->config->getInheritData()) {
            if (!$this->parent) {
                throw new RuntimeException('The form is configured to inherit its parent\'s data, but does not have a parent.');
            }

            return $this->parent->getNormData();
        }

        if (!$this->defaultDataSet) {
            if ($this->lockSetData) {
                throw new RuntimeException('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getNormData() if the form data has not already been set.');
            }

            $this->setData($this->config->getData());
        }

        return $this->normData;
    }

    /**
     * {@inheritdoc}
     */
    public function getViewData()
    {
        if ($this->config->getInheritData()) {
            if (!$this->parent) {
                throw new RuntimeException('The form is configured to inherit its parent\'s data, but does not have a parent.');
            }

            return $this->parent->getViewData();
        }

        if (!$this->defaultDataSet) {
            if ($this->lockSetData) {
                throw new RuntimeException('A cycle was detected. Listeners to the PRE_SET_DATA event must not call getViewData() if the form data has not already been set.');
            }

            $this->setData($this->config->getData());
        }

        return $this->viewData;
    }

    /**
     * {@inheritdoc}
     */
    public function getExtraData()
    {
        return $this->extraData;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        if (null !== $this->parent) {
            throw new RuntimeException('Only root forms should be initialized.');
        }

        // Guarantee that the *_SET_DATA events have been triggered once the
        // form is initialized. This makes sure that dynamically added or
        // removed fields are already visible after initialization.
        if (!$this->defaultDataSet) {
            $this->setData($this->config->getData());
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest($request = null)
    {
        $this->config->getRequestHandler()->handleRequest($this, $request);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function submit($submittedData, $clearMissing = true)
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('A form can only be submitted once');
        }

        // Initialize errors in the very beginning so that we don't lose any
        // errors added during listeners
        $this->errors = array();

        // Obviously, a disabled form should not change its data upon submission.
        if ($this->isDisabled()) {
            $this->submitted = true;

            return $this;
        }

        // The data must be initialized if it was not initialized yet.
        // This is necessary to guarantee that the *_SET_DATA listeners
        // are always invoked before submit() takes place.
        if (!$this->defaultDataSet) {
            $this->setData($this->config->getData());
        }

        // Treat false as NULL to support binding false to checkboxes.
        // Don't convert NULL to a string here in order to determine later
        // whether an empty value has been submitted or whether no value has
        // been submitted at all. This is important for processing checkboxes
        // and radio buttons with empty values.
        if (false === $submittedData) {
            $submittedData = null;
        } elseif (is_scalar($submittedData)) {
            $submittedData = (string) $submittedData;
        }

        $dispatcher = $this->config->getEventDispatcher();

        $modelData = null;
        $normData = null;
        $viewData = null;

        try {
            // Hook to change content of the data submitted by the browser
            if ($dispatcher->hasListeners(FormEvents::PRE_SUBMIT)) {
                $event = new FormEvent($this, $submittedData);
                $dispatcher->dispatch(FormEvents::PRE_SUBMIT, $event);
                $submittedData = $event->getData();
            }

            // Check whether the form is compound.
            // This check is preferable over checking the number of children,
            // since forms without children may also be compound.
            // (think of empty collection forms)
            if ($this->config->getCompound()) {
                if (null === $submittedData) {
                    $submittedData = array();
                }

                if (!\is_array($submittedData)) {
                    throw new TransformationFailedException('Compound forms expect an array or NULL on submission.');
                }

                foreach ($this->children as $name => $child) {
                    $isSubmitted = array_key_exists($name, $submittedData);

                    if ($isSubmitted || $clearMissing) {
                        $child->submit($isSubmitted ? $submittedData[$name] : null, $clearMissing);
                        unset($submittedData[$name]);

                        if (null !== $this->clickedButton) {
                            continue;
                        }

                        if ($child instanceof ClickableInterface && $child->isClicked()) {
                            $this->clickedButton = $child;

                            continue;
                        }

                        if (method_exists($child, 'getClickedButton') && null !== $child->getClickedButton()) {
                            $this->clickedButton = $child->getClickedButton();
                        }
                    }
                }

                $this->extraData = $submittedData;
            }

            // Forms that inherit their parents' data also are not processed,
            // because then it would be too difficult to merge the changes in
            // the child and the parent form. Instead, the parent form also takes
            // changes in the grandchildren (i.e. children of the form that inherits
            // its parent's data) into account.
            // (see InheritDataAwareIterator below)
            if (!$this->config->getInheritData()) {
                // If the form is compound, the default data in view format
                // is reused. The data of the children is merged into this
                // default data using the data mapper.
                // If the form is not compound, the submitted data is also the data in view format.
                $viewData = $this->config->getCompound() ? $this->viewData : $submittedData;

                if (FormUtil::isEmpty($viewData)) {
                    $emptyData = $this->config->getEmptyData();

                    if ($emptyData instanceof \Closure) {
                        /* @var \Closure $emptyData */
                        $emptyData = $emptyData($this, $viewData);
                    }

                    $viewData = $emptyData;
                }

                // Merge form data from children into existing view data
                // It is not necessary to invoke this method if the form has no children,
                // even if it is compound.
                if (\count($this->children) > 0) {
                    // Use InheritDataAwareIterator to process children of
                    // descendants that inherit this form's data.
                    // These descendants will not be submitted normally (see the check
                    // for $this->config->getInheritData() above)
                    $childrenIterator = new InheritDataAwareIterator($this->children);
                    $childrenIterator = new \RecursiveIteratorIterator($childrenIterator);
                    $this->config->getDataMapper()->mapFormsToData($childrenIterator, $viewData);
                }

                // Normalize data to unified representation
                $normData = $this->viewToNorm($viewData);

                // Hook to change content of the data in the normalized
                // representation
                if ($dispatcher->hasListeners(FormEvents::SUBMIT)) {
                    $event = new FormEvent($this, $normData);
                    $dispatcher->dispatch(FormEvents::SUBMIT, $event);
                    $normData = $event->getData();
                }

                // Synchronize representations - must not change the content!
                $modelData = $this->normToModel($normData);
                $viewData = $this->normToView($normData);
            }
        } catch (TransformationFailedException $e) {
            $this->transformationFailure = $e;

            // If $viewData was not yet set, set it to $submittedData so that
            // the erroneous data is accessible on the form.
            // Forms that inherit data never set any data, because the getters
            // forward to the parent form's getters anyway.
            if (null === $viewData && !$this->config->getInheritData()) {
                $viewData = $submittedData;
            }
        }

        $this->submitted = true;
        $this->modelData = $modelData;
        $this->normData = $normData;
        $this->viewData = $viewData;

        if ($dispatcher->hasListeners(FormEvents::POST_SUBMIT)) {
            $event = new FormEvent($this, $viewData);
            $dispatcher->dispatch(FormEvents::POST_SUBMIT, $event);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addError(FormError $error)
    {
        if (null === $error->getOrigin()) {
            $error->setOrigin($this);
        }

        if ($this->parent && $this->config->getErrorBubbling()) {
            $this->parent->addError($error);
        } else {
            $this->errors[] = $error;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isSubmitted()
    {
        return $this->submitted;
    }

    /**
     * {@inheritdoc}
     */
    public function isSynchronized()
    {
        return null === $this->transformationFailure;
    }

    /**
     * {@inheritdoc}
     */
    public function getTransformationFailure()
    {
        return $this->transformationFailure;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        foreach ($this->children as $child) {
            if (!$child->isEmpty()) {
                return false;
            }
        }

        return FormUtil::isEmpty($this->modelData) ||
            // arrays, countables
            ((\is_array($this->modelData) || $this->modelData instanceof \Countable) && 0 === \count($this->modelData)) ||
            // traversables that are not countable
            ($this->modelData instanceof \Traversable && 0 === iterator_count($this->modelData));
    }

    /**
     * {@inheritdoc}
     */
    public function isValid()
    {
        if (!$this->submitted) {
            throw new LogicException('Cannot check if an unsubmitted form is valid. Call Form::isSubmitted() before Form::isValid().');
        }

        if ($this->isDisabled()) {
            return true;
        }

        return 0 === \count($this->getErrors(true));
    }

    /**
     * Returns the button that was used to submit the form.
     *
     * @return Button|null The clicked button or NULL if the form was not
     *                     submitted
     */
    public function getClickedButton()
    {
        if ($this->clickedButton) {
            return $this->clickedButton;
        }

        if ($this->parent && method_exists($this->parent, 'getClickedButton')) {
            return $this->parent->getClickedButton();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($deep = false, $flatten = true)
    {
        $errors = $this->errors;

        // Copy the errors of nested forms to the $errors array
        if ($deep) {
            foreach ($this as $child) {
                /** @var FormInterface $child */
                if ($child->isSubmitted() && $child->isValid()) {
                    continue;
                }

                $iterator = $child->getErrors(true, $flatten);

                if (0 === \count($iterator)) {
                    continue;
                }

                if ($flatten) {
                    foreach ($iterator as $error) {
                        $errors[] = $error;
                    }
                } else {
                    $errors[] = $iterator;
                }
            }
        }

        return new FormErrorIterator($this, $errors);
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return iterator_to_array($this->children);
    }

    /**
     * {@inheritdoc}
     */
    public function add($child, $type = null, array $options = array())
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('You cannot add children to a submitted form');
        }

        if (!$this->config->getCompound()) {
            throw new LogicException('You cannot add children to a simple form. Maybe you should set the option "compound" to true?');
        }

        // Obtain the view data
        $viewData = null;

        // If setData() is currently being called, there is no need to call
        // mapDataToForms() here, as mapDataToForms() is called at the end
        // of setData() anyway. Not doing this check leads to an endless
        // recursion when initializing the form lazily and an event listener
        // (such as ResizeFormListener) adds fields depending on the data:
        //
        //  * setData() is called, the form is not initialized yet
        //  * add() is called by the listener (setData() is not complete, so
        //    the form is still not initialized)
        //  * getViewData() is called
        //  * setData() is called since the form is not initialized yet
        //  * ... endless recursion ...
        //
        // Also skip data mapping if setData() has not been called yet.
        // setData() will be called upon form initialization and data mapping
        // will take place by then.
        if (!$this->lockSetData && $this->defaultDataSet && !$this->config->getInheritData()) {
            $viewData = $this->getViewData();
        }

        if (!$child instanceof FormInterface) {
            if (!\is_string($child) && !\is_int($child)) {
                throw new UnexpectedTypeException($child, 'string, integer or Symfony\Component\Form\FormInterface');
            }

            if (null !== $type && !\is_string($type) && !$type instanceof FormTypeInterface) {
                throw new UnexpectedTypeException($type, 'string or Symfony\Component\Form\FormTypeInterface');
            }

            // Never initialize child forms automatically
            $options['auto_initialize'] = false;

            if (null === $type && null === $this->config->getDataClass()) {
                $type = 'Symfony\Component\Form\Extension\Core\Type\TextType';
            }

            if (null === $type) {
                $child = $this->config->getFormFactory()->createForProperty($this->config->getDataClass(), $child, null, $options);
            } else {
                $child = $this->config->getFormFactory()->createNamed($child, $type, null, $options);
            }
        } elseif ($child->getConfig()->getAutoInitialize()) {
            throw new RuntimeException(sprintf('Automatic initialization is only supported on root forms. You should set the "auto_initialize" option to false on the field "%s".', $child->getName()));
        }

        $this->children[$child->getName()] = $child;

        $child->setParent($this);

        if (!$this->lockSetData && $this->defaultDataSet && !$this->config->getInheritData()) {
            $iterator = new InheritDataAwareIterator(new \ArrayIterator(array($child->getName() => $child)));
            $iterator = new \RecursiveIteratorIterator($iterator);
            $this->config->getDataMapper()->mapDataToForms($viewData, $iterator);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($name)
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('You cannot remove children from a submitted form');
        }

        if (isset($this->children[$name])) {
            if (!$this->children[$name]->isSubmitted()) {
                $this->children[$name]->setParent(null);
            }

            unset($this->children[$name]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function has($name)
    {
        return isset($this->children[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        if (isset($this->children[$name])) {
            return $this->children[$name];
        }

        throw new OutOfBoundsException(sprintf('Child "%s" does not exist.', $name));
    }

    /**
     * Returns whether a child with the given name exists (implements the \ArrayAccess interface).
     *
     * @param string $name The name of the child
     *
     * @return bool
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * Returns the child with the given name (implements the \ArrayAccess interface).
     *
     * @param string $name The name of the child
     *
     * @return FormInterface The child form
     *
     * @throws \OutOfBoundsException if the named child does not exist
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * Adds a child to the form (implements the \ArrayAccess interface).
     *
     * @param string        $name  Ignored. The name of the child is used
     * @param FormInterface $child The child to be added
     *
     * @throws AlreadySubmittedException if the form has already been submitted
     * @throws LogicException            when trying to add a child to a non-compound form
     *
     * @see self::add()
     */
    public function offsetSet($name, $child)
    {
        $this->add($child);
    }

    /**
     * Removes the child with the given name from the form (implements the \ArrayAccess interface).
     *
     * @param string $name The name of the child to remove
     *
     * @throws AlreadySubmittedException if the form has already been submitted
     */
    public function offsetUnset($name)
    {
        $this->remove($name);
    }

    /**
     * Returns the iterator for this group.
     *
     * @return \Traversable|FormInterface[]
     */
    public function getIterator()
    {
        return $this->children;
    }

    /**
     * Returns the number of form children (implements the \Countable interface).
     *
     * @return int The number of embedded form children
     */
    public function count()
    {
        return \count($this->children);
    }

    /**
     * {@inheritdoc}
     */
    public function createView(FormView $parent = null)
    {
        if (null === $parent && $this->parent) {
            $parent = $this->parent->createView();
        }

        $type = $this->config->getType();
        $options = $this->config->getOptions();

        // The methods createView(), buildView() and finishView() are called
        // explicitly here in order to be able to override either of them
        // in a custom resolved form type.
        $view = $type->createView($this, $parent);

        $type->buildView($view, $this, $options);

        foreach ($this->children as $name => $child) {
            $view->children[$name] = $child->createView($view);
        }

        $type->finishView($view, $this, $options);

        return $view;
    }

    /**
     * Normalizes the value if a normalization transformer is set.
     *
     * @param mixed $value The value to transform
     *
     * @return mixed
     *
     * @throws TransformationFailedException If the value cannot be transformed to "normalized" format
     */
    private function modelToNorm($value)
    {
        try {
            foreach ($this->config->getModelTransformers() as $transformer) {
                $value = $transformer->transform($value);
            }
        } catch (TransformationFailedException $exception) {
            throw new TransformationFailedException('Unable to transform value for property path "'.$this->getPropertyPath().'": '.$exception->getMessage(), $exception->getCode(), $exception);
        }

        return $value;
    }

    /**
     * Reverse transforms a value if a normalization transformer is set.
     *
     * @param string $value The value to reverse transform
     *
     * @return mixed
     *
     * @throws TransformationFailedException If the value cannot be transformed to "model" format
     */
    private function normToModel($value)
    {
        try {
            $transformers = $this->config->getModelTransformers();

            for ($i = \count($transformers) - 1; $i >= 0; --$i) {
                $value = $transformers[$i]->reverseTransform($value);
            }
        } catch (TransformationFailedException $exception) {
            throw new TransformationFailedException('Unable to reverse value for property path "'.$this->getPropertyPath().'": '.$exception->getMessage(), $exception->getCode(), $exception);
        }

        return $value;
    }

    /**
     * Transforms the value if a value transformer is set.
     *
     * @param mixed $value The value to transform
     *
     * @return mixed
     *
     * @throws TransformationFailedException If the value cannot be transformed to "view" format
     */
    private function normToView($value)
    {
        // Scalar values should  be converted to strings to
        // facilitate differentiation between empty ("") and zero (0).
        // Only do this for simple forms, as the resulting value in
        // compound forms is passed to the data mapper and thus should
        // not be converted to a string before.
        if (!$this->config->getViewTransformers() && !$this->config->getCompound()) {
            return null === $value || is_scalar($value) ? (string) $value : $value;
        }

        try {
            foreach ($this->config->getViewTransformers() as $transformer) {
                $value = $transformer->transform($value);
            }
        } catch (TransformationFailedException $exception) {
            throw new TransformationFailedException('Unable to transform value for property path "'.$this->getPropertyPath().'": '.$exception->getMessage(), $exception->getCode(), $exception);
        }

        return $value;
    }

    /**
     * Reverse transforms a value if a value transformer is set.
     *
     * @param string $value The value to reverse transform
     *
     * @return mixed
     *
     * @throws TransformationFailedException If the value cannot be transformed to "normalized" format
     */
    private function viewToNorm($value)
    {
        $transformers = $this->config->getViewTransformers();

        if (!$transformers) {
            return '' === $value ? null : $value;
        }

        try {
            for ($i = \count($transformers) - 1; $i >= 0; --$i) {
                $value = $transformers[$i]->reverseTransform($value);
            }
        } catch (TransformationFailedException $exception) {
            throw new TransformationFailedException('Unable to reverse value for property path "'.$this->getPropertyPath().'": '.$exception->getMessage(), $exception->getCode(), $exception);
        }

        return $value;
    }
}
