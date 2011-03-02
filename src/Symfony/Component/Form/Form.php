<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
use Symfony\Component\Form\FieldFactory\FieldFactoryInterface;
use Symfony\Component\Form\Filter\FilterInterface;

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
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class Form extends Field implements \IteratorAggregate, FormInterface, FilterInterface
{
    /**
     * Contains all the fields of this group
     * @var array
     */
    private $fields = array();

    /**
     * Contains the names of submitted values who don't belong to any fields
     * @var array
     */
    private $extraFields = array();

    /**
     * Stores the class that the data of this form must be instances of
     * @var string
     */
    private $dataClass;

    /**
     * Stores the constructor closure for creating new domain object instances
     * @var \Closure
     */
    private $dataConstructor;

    private $modifyByReference = true;

    private $validator;

    private $validationGroups;

    private $virtual;

    private $csrfFieldName;

    private $csrfProvider;

    public function __construct($key = null)
    {
        parent::__construct($key);

        $this->appendFilter($this);
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

            // TODO throw exception if nothing was returned
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

        return $this;
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
     * Initializes the field group with an object to operate on
     *
     * @see FieldInterface
     */
    public function setData($data)
    {
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

        return $this;
    }

    public function filterSetData($data)
    {
        if (null === $this->getValueTransformer() && null === $this->getNormalizationTransformer()) {
            // Empty values must be converted to objects or arrays so that
            // they can be read by PropertyPath in the child fields
            if (empty($data)) {
                if ($this->dataConstructor) {
                    $constructor = $this->dataConstructor;
                    $data = $constructor();
                } else if ($this->dataClass) {
                    $class = $this->dataClass;
                    $data = new $class();
                } else {
                    $data = array();
                }
            }
        }

        return $data;
    }

    public function filterBoundDataFromClient($data)
    {
        if (!is_array($data)) {
            throw new UnexpectedTypeException($data, 'array');
        }

        foreach ($this->fields as $key => $field) {
            if (!isset($data[$key])) {
                $data[$key] = null;
            }
        }

        foreach ($data as $key => $value) {
            if ($this->has($key)) {
                $this->fields[$key]->submit($value);
            }
        }

        $data = $this->getTransformedData();

        $this->writeObject($data);

        return $data;
    }

    public function getSupportedFilters()
    {
        return array(
            Filters::filterSetData,
            Filters::filterBoundDataFromClient,
        );
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
        // set and reverse transform the data
        parent::submit($data);

        $this->extraFields = array();

        foreach ((array)$data as $key => $value) {
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
    protected function preprocessData($data)
    {
        if ($this->dataPreprocessor) {
            return $this->dataPreprocessor->processData($data);
        }

        return $data;
    }

    public function setVirtual($virtual)
    {
        $this->virtual = $virtual;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isVirtual()
    {
        return $this->virtual;
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

                if ($this->has($pathIterator->current())) {
                    $this->get($pathIterator->current())->addError($error, $pathIterator);

                    return;
                }
            } else if ($error instanceof DataError) {
                $iterator = new RecursiveFieldIterator($this);
                $iterator = new \RecursiveIteratorIterator($iterator);

                foreach ($iterator as $field) {
                    if (null !== ($fieldPath = $field->getPropertyPath())) {
                        if ($fieldPath->getElement(0) === $pathIterator->current()) {
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

    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Returns the validator used by the form
     *
     * @return ValidatorInterface  The validator instance
     */
    public function getValidator()
    {
        return $this->validator;
    }

    public function setValidationGroups($validationGroups)
    {
        $this->validationGroups = empty($validationGroups) ? null : (array)$validationGroups;

        return $this;
    }

    /**
     * Returns the validation groups validated by the form
     *
     * @return array  A list of validation groups or null
     */
    public function getValidationGroups()
    {
        $groups = $this->validationGroups;

        if (!$groups && $this->hasParent()) {
            $groups = $this->getParent()->getValidationGroups();
        }

        return $groups;
    }

    public function enableCsrfProtection(CsrfProviderInterface $provider, $fieldName = '_token')
    {
        $this->csrfProvider = $provider;
        $this->csrfFieldName = $fieldName;

        $token = $provider->generateCsrfToken(get_class($this));

        // FIXME
//        $this->add(new HiddenField($fieldName, array('data' => $token)));
    }

    public function disableCsrfProtection()
    {
        if ($this->isCsrfProtected()) {
            $this->remove($this->csrfFieldName);
            $this->csrfProvider = null;
            $this->csrfFieldName = null;
        }
    }

    /**
     * Returns the name used for the CSRF protection field
     *
     * @return string  The field name
     */
    public function getCsrfFieldName()
    {
        return $this->csrfFieldName;
    }

    /**
     * Returns the provider used for generating and validating CSRF tokens
     *
     * @return CsrfProviderInterface  The provider instance
     */
    public function getCsrfProvider()
    {
        return $this->csrfProvider;
    }

    /**
     * @return true if this form is CSRF protected
     */
    public function isCsrfProtected()
    {
        return $this->csrfFieldName && $this->has($this->csrfFieldName);
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
            $token = $this->get($this->csrfFieldName)->getDisplayedData();

            return $this->csrfProvider->isCsrfTokenValid(get_class($this), $token);
        }
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
        if (!$this->getKey()) {
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
     * @deprecated
     */
    private function getName()
    {
        return null === $this->getParent() ? $this->getKey() : $this->getParent()->getName().'['.$this->key.']';
    }

    /**
     * @deprecated
     */
    private function getId()
    {
        return null === $this->getParent() ? $this->getKey() : $this->getParent()->getId().'_'.$this->key;
    }

    /**
     * Validates the form and its domain object
     *
     * @throws FormException  If the option "validator" was not set
     */
    public function validate()
    {
        if (null === $this->validator) {
            throw new MissingOptionsException('A validator is required for validating', array('validator'));
        }

        // Validate the form in group "Default"
        // Validation of the data in the custom group is done by validateData(),
        // which is constrained by the Execute constraint
        if ($violations = $this->validator->validate($this)) {
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
    public function setDataClass($class)
    {
        $this->dataClass = $class;

        return $this;
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

    public function setDataConstructor($dataConstructor)
    {
        $this->dataConstructor = $dataConstructor;

        return $this;
    }

    public function getDataConstructor()
    {
        return $this->dataConstructor;
    }

    public function setFieldFactory(FieldFactoryInterface $fieldFactory = null)
    {
        $this->fieldFactory = $fieldFactory;

        return $this;
    }

    /**
     * Returns a factory for automatically creating fields based on metadata
     * available for a form's object
     *
     * @return FieldFactoryInterface  The factory
     */
    public function getFieldFactory()
    {
        return $this->fieldFactory;
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
        if (!is_object($objectOrArray) || !$isReference || !$this->modifyByReference) {
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

    public function setModifyByReference($modifyByReference)
    {
        $this->modifyByReference = $modifyByReference;

        return $this;
    }

    public function isModifiedByReference()
    {
        return $this->modifyByReference;
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
