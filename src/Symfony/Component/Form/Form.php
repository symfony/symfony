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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\MissingOptionsException;
use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\DanglingFieldException;
use Symfony\Component\Form\Exception\FieldDefinitionException;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;

/**
 * Form represents a form.
 *
 * A form is composed of a validator schema and a widget form schema.
 *
 * Form also takes care of CSRF protection by default.
 *
 * A CSRF secret can be any random string. If set to false, it disables the
 * CSRF protection, and if set to null, it forces the form to use the global
 * CSRF secret. If the global CSRF secret is also null, then a random one
 * is generated on the fly.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class Form extends Field implements \IteratorAggregate, FormInterface
{
    /**
     * Contains all the fields of this group
     * @var array
     */
    protected $fields = array();

    /**
     * Contains the names of submitted values who don't belong to any fields
     * @var array
     */
    protected $extraFields = array();

    /**
     * Stores the class that the data of this form must be instances of
     * @var string
     */
    protected $dataClass;

    /**
     * Stores the constructor closure for creating new domain object instances
     * @var \Closure
     */
    protected $dataConstructor;

    /**
     * The context used when creating the form
     * @var FormContext
     */
    protected $context = null;

    /**
     * Creates a new form with the options stored in the given context
     *
     * @param  FormContextInterface $context
     * @param  string $name
     * @param  array $options
     * @return Form
     */
    public static function create(FormContextInterface $context, $name = null, array $options = array())
    {
        return new static($name, array_merge($context->getOptions(), $options));
    }

    /**
     * Constructor.
     *
     * @param string $name
     * @param array $options
     */
    public function __construct($name = null, array $options = array())
    {
        $this->addOption('data_class');
        $this->addOption('data_constructor');
        $this->addOption('csrf_field_name', '_token');
        $this->addOption('csrf_provider');
        $this->addOption('field_factory');
        $this->addOption('validation_groups');
        $this->addOption('virtual', false);
        $this->addOption('validator');
        $this->addOption('context');
        $this->addOption('by_reference', true);

        if (isset($options['validation_groups'])) {
            $options['validation_groups'] = (array)$options['validation_groups'];
        }

        if (isset($options['data_class'])) {
            $this->dataClass = $options['data_class'];
        }

        if (isset($options['data_constructor'])) {
            $this->dataConstructor = $options['data_constructor'];
        }

        parent::__construct($name, $options);

        // Enable CSRF protection
        if ($this->getOption('csrf_provider')) {
            if (!$this->getOption('csrf_provider') instanceof CsrfProviderInterface) {
                throw new FormException('The object passed to the "csrf_provider" option must implement CsrfProviderInterface');
            }

            $fieldName = $this->getOption('csrf_field_name');
            $token = $this->getOption('csrf_provider')->generateCsrfToken(get_class($this));

            $this->add(new HiddenField($fieldName, array('data' => $token)));
        }
    }

    /**
     * Clones this group
     */
    public function __clone()
    {
        foreach ($this->fields as $name => $field) {
            $field = clone $field;
            // this condition is only to "bypass" a PHPUnit bug with mocks
            if (null !== $field->getParent()) {
                $field->setParent($this);
            }
            $this->fields[$name] = $field;
        }
    }

    /**
     * Adds a new field to this group. A field must have a unique name within
     * the group. Otherwise the existing field is overwritten.
     *
     * If you add a nested group, this group should also be represented in the
     * object hierarchy. If you want to add a group that operates on the same
     * hierarchy level, use merge().
     *
     * <code>
     * class Entity
     * {
     *   public $location;
     * }
     *
     * class Location
     * {
     *   public $longitude;
     *   public $latitude;
     * }
     *
     * $entity = new Entity();
     * $entity->location = new Location();
     *
     * $form = new Form('entity', $entity, $validator);
     *
     * $locationGroup = new Form('location');
     * $locationGroup->add(new TextField('longitude'));
     * $locationGroup->add(new TextField('latitude'));
     *
     * $form->add($locationGroup);
     * </code>
     *
     * @param FieldInterface|string $field
     * @return FieldInterface
     */
    public function add($field)
    {
        if ($this->isSubmitted()) {
            throw new AlreadySubmittedException('You cannot add fields after submitting a form');
        }

        // if the field is given as string, ask the field factory of the form
        // to create a field
        if (!$field instanceof FieldInterface) {
            if (!is_string($field)) {
                throw new UnexpectedTypeException($field, 'FieldInterface or string');
            }

            $factory = $this->getFieldFactory();

            if (!$factory) {
                throw new FormException('A field factory must be set to automatically create fields');
            }

            $class = $this->getDataClass();

            if (!$class) {
                throw new FormException('The data class must be set to automatically create fields');
            }

            $options = func_num_args() > 1 ? func_get_arg(1) : array();
            $field = $factory->getInstance($class, $field, $options);
        }

        if ('' === $field->getKey() || null === $field->getKey()) {
            throw new FieldDefinitionException('You cannot add anonymous fields');
        }

        $this->fields[$field->getKey()] = $field;

        $field->setParent($this);

        $data = $this->getTransformedData();

        // if the property "data" is NULL, getTransformedData() returns an empty
        // string
        if (!empty($data)) {
            $field->readProperty($data);
        }

        return $field;
    }

    /**
     * Removes the field with the given key.
     *
     * @param string $key
     */
    public function remove($key)
    {
        $this->fields[$key]->setParent(null);

        unset($this->fields[$key]);
    }

    /**
     * Returns whether a field with the given key exists.
     *
     * @param  string $key
     * @return Boolean
     */
    public function has($key)
    {
        return isset($this->fields[$key]);
    }

    /**
     * Returns the field with the given key.
     *
     * @param  string $key
     * @return FieldInterface
     */
    public function get($key)
    {
        if (isset($this->fields[$key])) {
            return $this->fields[$key];
        }

        throw new \InvalidArgumentException(sprintf('Field "%s" does not exist.', $key));
    }

    /**
     * Returns all fields in this group
     *
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Returns an array of visible fields from the current schema.
     *
     * @return array
     */
    public function getVisibleFields()
    {
        return $this->getFieldsByVisibility(false, false);
    }

    /**
     * Returns an array of visible fields from the current schema.
     *
     * This variant of the method will recursively get all the
     * fields from the nested forms or field groups
     *
     * @return array
     */
    public function getAllVisibleFields()
    {
        return $this->getFieldsByVisibility(false, true);
    }

    /**
     * Returns an array of hidden fields from the current schema.
     *
     * @return array
     */
    public function getHiddenFields()
    {
        return $this->getFieldsByVisibility(true, false);
    }

    /**
     * Returns an array of hidden fields from the current schema.
     *
     * This variant of the method will recursively get all the
     * fields from the nested forms or field groups
     *
     * @return array
     */
    public function getAllHiddenFields()
    {
        return $this->getFieldsByVisibility(true, true);
    }

    /**
     * Returns a filtered array of fields from the current schema.
     *
     * @param Boolean $hidden Whether to return hidden fields only or visible fields only
     * @param Boolean $recursive Whether to recur through embedded schemas
     *
     * @return array
     */
    protected function getFieldsByVisibility($hidden, $recursive)
    {
        $fields = array();
        $hidden = (Boolean)$hidden;

        foreach ($this->fields as $field) {
            if ($field instanceof Form && $recursive) {
                $fields = array_merge($fields, $field->getFieldsByVisibility($hidden, $recursive));
            } else if ($hidden === $field->isHidden()) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Initializes the field group with an object to operate on
     *
     * @see FieldInterface
     */
    public function setData($data)
    {
        if (empty($data)) {
            if ($this->dataConstructor) {
                $constructor = $this->dataConstructor;
                $data = $constructor();
            } else if ($this->dataClass) {
                $class = $this->dataClass;
                $data = new $class();
            }
        }

        parent::setData($data);

        // get transformed data and pass its values to child fields
        $data = $this->getTransformedData();

        if (!empty($data) && !is_array($data) && !is_object($data)) {
            throw new \InvalidArgumentException(sprintf('Expected argument of type object or array, %s given', gettype($data)));
        }

        if (!empty($data)) {
            if ($this->dataClass && !$data instanceof $this->dataClass) {
                throw new FormException(sprintf('Form data should be instance of %s', $this->dataClass));
            }

            $this->readObject($data);
        }
    }

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @see FieldInterface
     * @return array of field name => value
     */
    public function getDisplayedData()
    {
        $values = array();

        foreach ($this->fields as $key => $field) {
            $values[$key] = $field->getDisplayedData();
        }

        return $values;
    }

    /**
     * Binds POST data to the field, transforms and validates it.
     *
     * @param  string|array $data  The POST data
     */
    public function submit($data)
    {
        if (null === $data) {
            $data = array();
        }

        if (!is_array($data)) {
            throw new UnexpectedTypeException($data, 'array');
        }

        // remember for later
        $submittedData = $data;

        foreach ($this->fields as $key => $field) {
            if (!isset($data[$key])) {
                $data[$key] = null;
            }
        }

        $data = $this->preprocessData($data);

        foreach ($data as $key => $value) {
            if ($this->has($key)) {
                $this->fields[$key]->submit($value);
            }
        }

        $data = $this->getTransformedData();

        $this->writeObject($data);

        // set and reverse transform the data
        parent::submit($data);

        $this->extraFields = array();

        foreach ($submittedData as $key => $value) {
            if (!$this->has($key)) {
                $this->extraFields[] = $key;
            }
        }
    }

    /**
     * Updates the child fields from the properties of the given data
     *
     * This method calls readProperty() on all child fields that have a
     * property path set. If a child field has no property path set but
     * implements FormInterface, writeProperty() is called on its
     * children instead.
     *
     * @param array|object $objectOrArray
     */
    protected function readObject(&$objectOrArray)
    {
        $iterator = new RecursiveFieldIterator($this);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $field) {
            $field->readProperty($objectOrArray);
        }
    }

    /**
     * Updates all properties of the given data from the child fields
     *
     * This method calls writeProperty() on all child fields that have a property
     * path set. If a child field has no property path set but implements
     * FormInterface, writeProperty() is called on its children instead.
     *
     * @param array|object $objectOrArray
     */
    protected function writeObject(&$objectOrArray)
    {
        $iterator = new RecursiveFieldIterator($this);
        $iterator = new \RecursiveIteratorIterator($iterator);

        foreach ($iterator as $field) {
            $field->writeProperty($objectOrArray);
        }
    }

    /**
     * Processes the submitted data before it is passed to the individual fields
     *
     * The data is in the user format.
     *
     * @param  array $data
     * @return array
     */
    protected function preprocessData(array $data)
    {
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function isVirtual()
    {
        return $this->getOption('virtual');
    }

    /**
     * Returns whether this form was submitted with extra fields
     *
     * @return Boolean
     */
    public function isSubmittedWithExtraFields()
    {
        // TODO: integrate the field names in the error message
        return count($this->extraFields) > 0;
    }

    /**
     * Returns whether the field is valid.
     *
     * @return Boolean
     */
    public function isValid()
    {
        if (!parent::isValid()) {
            return false;
        }

        foreach ($this->fields as $field) {
            if (!$field->isValid()) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function addError(Error $error, PropertyPathIterator $pathIterator = null)
    {
        if (null !== $pathIterator) {
            if ($error instanceof FieldError && $pathIterator->hasNext()) {
                $pathIterator->next();

                if ($pathIterator->isProperty() && $pathIterator->current() === 'fields') {
                    $pathIterator->next();
                }

                if ($this->has($pathIterator->current()) && !$this->get($pathIterator->current())->isHidden()) {
                    $this->get($pathIterator->current())->addError($error, $pathIterator);

                    return;
                }
            } else if ($error instanceof DataError) {
                $iterator = new RecursiveFieldIterator($this);
                $iterator = new \RecursiveIteratorIterator($iterator);

                foreach ($iterator as $field) {
                    if (null !== ($fieldPath = $field->getPropertyPath())) {
                        if ($fieldPath->getElement(0) === $pathIterator->current() && !$field->isHidden()) {
                            if ($pathIterator->hasNext()) {
                                $pathIterator->next();
                            }

                            $field->addError($error, $pathIterator);

                            return;
                        }
                    }
                }
            }
        }

        parent::addError($error);
    }

    /**
     * Returns whether the field requires a multipart form.
     *
     * @return Boolean
     */
    public function isMultipart()
    {
        foreach ($this->fields as $field) {
            if ($field->isMultipart()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns true if the field exists (implements the \ArrayAccess interface).
     *
     * @param string $key The key of the field
     *
     * @return Boolean true if the widget exists, false otherwise
     */
    public function offsetExists($key)
    {
        return $this->has($key);
    }

    /**
     * Returns the form field associated with the name (implements the \ArrayAccess interface).
     *
     * @param string $key The offset of the value to get
     *
     * @return Field A form field instance
     */
    public function offsetGet($key)
    {
        return $this->get($key);
    }

    /**
     * Throws an exception saying that values cannot be set (implements the \ArrayAccess interface).
     *
     * @param string $offset (ignored)
     * @param string $value (ignored)
     *
     * @throws \LogicException
     */
    public function offsetSet($key, $field)
    {
        throw new \LogicException('Use the method add() to add fields');
    }

    /**
     * Throws an exception saying that values cannot be unset (implements the \ArrayAccess interface).
     *
     * @param string $key
     *
     * @throws \LogicException
     */
    public function offsetUnset($key)
    {
        return $this->remove($key);
    }

    /**
     * Returns the iterator for this group.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->fields);
    }

    /**
     * Returns the number of form fields (implements the \Countable interface).
     *
     * @return integer The number of embedded form fields
     */
    public function count()
    {
        return count($this->fields);
    }

    /**
     * Returns a factory for automatically creating fields based on metadata
     * available for a form's object
     *
     * @return FieldFactoryInterface  The factory
     */
    public function getFieldFactory()
    {
        return $this->getOption('field_factory');
    }

    /**
     * Returns the validator used by the form
     *
     * @return ValidatorInterface  The validator instance
     */
    public function getValidator()
    {
        return $this->getOption('validator');
    }

    /**
     * Returns the validation groups validated by the form
     *
     * @return array  A list of validation groups or null
     */
    public function getValidationGroups()
    {
        $groups = $this->getOption('validation_groups');

        if (!$groups && $this->hasParent()) {
            $groups = $this->getParent()->getValidationGroups();
        }

        return $groups;
    }

    /**
     * Returns the name used for the CSRF protection field
     *
     * @return string  The field name
     */
    public function getCsrfFieldName()
    {
        return $this->getOption('csrf_field_name');
    }

    /**
     * Returns the provider used for generating and validating CSRF tokens
     *
     * @return CsrfProviderInterface  The provider instance
     */
    public function getCsrfProvider()
    {
        return $this->getOption('csrf_provider');
    }

    /**
     * Binds a request to the form
     *
     * If the request was a POST request, the data is submitted to the form,
     * transformed and written into the form data (an object or an array).
     * You can set the form data by passing it in the second parameter
     * of this method or by passing it in the "data" option of the form's
     * constructor.
     *
     * @param Request $request    The request to bind to the form
     * @param array|object $data  The data from which to read default values
     *                            and where to write submitted values
     */
    public function bind(Request $request, $data = null)
    {
        if (!$this->getName()) {
            throw new FormException('You cannot bind anonymous forms. Please give this form a name');
        }

        // Store object from which to read the default values and where to
        // write the submitted values
        if (null !== $data) {
            $this->setData($data);
        }

        // Store the submitted data in case of a post request
        if ('POST' == $request->getMethod()) {
            $values = $request->request->get($this->getName(), array());
            $files = $request->files->get($this->getName(), array());

            $this->submit(self::deepArrayUnion($values, $files));

            $this->validate();
        }
    }

    /**
     * Validates the form and its domain object
     *
     * @throws FormException  If the option "validator" was not set
     */
    public function validate()
    {
        $validator = $this->getOption('validator');

        if (null === $validator) {
            throw new MissingOptionsException('The option "validator" is required for validating', array('validator'));
        }

        // Validate the form in group "Default"
        // Validation of the data in the custom group is done by validateData(),
        // which is constrained by the Execute constraint
        if ($violations = $validator->validate($this)) {
            foreach ($violations as $violation) {
                $propertyPath = new PropertyPath($violation->getPropertyPath());
                $iterator = $propertyPath->getIterator();
                $template = $violation->getMessageTemplate();
                $parameters = $violation->getMessageParameters();

                if ($iterator->current() == 'data') {
                    $iterator->next(); // point at the first data element
                    $error = new DataError($template, $parameters);
                } else {
                    $error = new FieldError($template, $parameters);
                }

                $this->addError($error, $iterator);
            }
        }
    }

    /**
     * @return true if this form is CSRF protected
     */
    public function isCsrfProtected()
    {
        return $this->has($this->getOption('csrf_field_name'));
    }

    /**
     * Returns whether the CSRF token is valid
     *
     * @return Boolean
     */
    public function isCsrfTokenValid()
    {
        if (!$this->isCsrfProtected()) {
            return true;
        } else {
            $token = $this->get($this->getOption('csrf_field_name'))->getDisplayedData();

            return $this->getOption('csrf_provider')->isCsrfTokenValid(get_class($this), $token);
        }
    }

    /**
     * Returns whether the maximum POST size was reached in this request.
     *
     * @return Boolean
     */
    public function isPostMaxSizeReached()
    {
        if ($this->isRoot() && isset($_SERVER['CONTENT_LENGTH'])) {
            $length = (int) $_SERVER['CONTENT_LENGTH'];
            $max = trim(ini_get('post_max_size'));

            switch (strtolower(substr($max, -1))) {
                // The 'G' modifier is available since PHP 5.1.0
                case 'g':
                    $max *= 1024;
                case 'm':
                    $max *= 1024;
                case 'k':
                    $max *= 1024;
            }

            return $length > $max;
        }

        return false;
    }

    /**
     * Sets the class that object bound to this form must be instances of
     *
     * @param string  A fully qualified class name
     */
    protected function setDataClass($class)
    {
        $this->dataClass = $class;
    }

    /**
     * Returns the class that object must have that are bound to this form
     *
     * @return string  A fully qualified class name
     */
    public function getDataClass()
    {
        return $this->dataClass;
    }

    /**
     * Returns the context used when creating this form
     *
     * @return FormContext  The context instance
     */
    public function getContext()
    {
        return $this->getOption('context');
    }

    /**
     * Validates the data of this form
     *
     * This method is called automatically during the validation process.
     *
     * @param ExecutionContext $context  The current validation context
     */
    public function validateData(ExecutionContext $context)
    {
        if (is_object($this->getData()) || is_array($this->getData())) {
            $groups = $this->getValidationGroups();
            $propertyPath = $context->getPropertyPath();
            $graphWalker = $context->getGraphWalker();

            if (null === $groups) {
                $groups = array(null);
            }

            // The Execute constraint is called on class level, so we need to
            // set the property manually
            $context->setCurrentProperty('data');

            // Adjust the property path accordingly
            if (!empty($propertyPath)) {
                $propertyPath .= '.';
            }

            $propertyPath .= 'data';

            foreach ($groups as $group) {
                $graphWalker->walkReference($this->getData(), $group, $propertyPath, true);
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function writeProperty(&$objectOrArray)
    {
        $isReference = false;

        // If the data is identical to the value in $objectOrArray, we are
        // dealing with a reference
        if ($this->getPropertyPath() !== null) {
            $isReference = $this->getData() === $this->getPropertyPath()->getValue($objectOrArray);
        }

        // Don't write into $objectOrArray if $objectOrArray is an object,
        // $isReference is true (see above) and the option "by_reference" is
        // true as well
        if (!is_object($objectOrArray) || !$isReference || !$this->getOption('by_reference')) {
            parent::writeProperty($objectOrArray);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty()
    {
        foreach ($this->fields as $field) {
            if (!$field->isEmpty()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Merges two arrays without reindexing numeric keys.
     *
     * @param array $array1 An array to merge
     * @param array $array2 An array to merge
     *
     * @return array The merged array
     */
    static protected function deepArrayUnion($array1, $array2)
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = self::deepArrayUnion($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }
}
