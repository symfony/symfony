<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\InvalidPropertyException;
use Symfony\Component\Form\Exception\PropertyAccessDeniedException;
use Symfony\Component\Form\Exception\NotBoundException;
use Symfony\Component\Form\Exception\NotValidException;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\Form\ValueTransformer\TransformationFailedException;
use Symfony\Component\I18N\TranslatorInterface;

abstract class Field extends Configurable implements FieldInterface
{
    /**
     * The object used for generating HTML code
     * @var HtmlGeneratorInterface
     */
    protected $generator = null;

    protected $taintedData = null;
    protected $locale = null;
    protected $translator = null;

    private $errors = array();
    private $key = '';
    private $parent = null;
    private $renderer = null;
    private $bound = false;
    private $required = null;
    private $data = null;
    private $transformedData = null;
    private $valueTransformer = null;
    private $propertyPath = null;

    public function __construct($key, array $options = array())
    {
        $this->addOption('trim', true);
        $this->addOption('required', true);
        $this->addOption('disabled', false);
        $this->addOption('property_path', (string)$key);

        $this->key = (string)$key;
        $this->generator = new HtmlGenerator();

        if ($this->locale === null) {
            $this->locale = class_exists('\Locale', false) ? \Locale::getDefault() : 'en';
        }

        parent::__construct($options);

        $this->transformedData = $this->transform($this->data);
        $this->required = $this->getOption('required');

        $this->setPropertyPath($this->getOption('property_path'));
    }

    /**
     * Clones this field.
     */
    public function __clone()
    {
        // TODO
    }

    /**
     * Returns the data of the field as it is displayed to the user.
     *
     * @return string|array  When the field is not bound, the transformed
     *                       default data is returned. When the field is bound,
     *                       the bound data is returned.
     */
    public function getDisplayedData()
    {
        return $this->getTransformedData();
    }

    /**
     * Returns the data transformed by the value transformer
     *
     * @return string
     */
    protected function getTransformedData()
    {
        return $this->transformedData;
    }

    /**
     * {@inheritDoc}
     */
    public function setPropertyPath($propertyPath)
    {
        $this->propertyPath = $propertyPath === null || $propertyPath === '' ? null : new PropertyPath($propertyPath);
    }

    /**
     * {@inheritDoc}
     */
    public function getPropertyPath()
    {
        return $this->propertyPath;
    }

    /**
     * {@inheritDoc}
     */
    public function setKey($key)
    {
        $this->key = (string)$key;
    }

    /**
     * {@inheritDoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return is_null($this->parent) ? $this->key : $this->parent->getName().'['.$this->key.']';
    }

    /**
     * {@inheritDoc}
     */
    public function getId()
    {
        return is_null($this->parent) ? $this->key : $this->parent->getId().'_'.$this->key;
    }

    /**
     * {@inheritDoc}
     */
    public function setRequired($required)
    {
        $this->required = $required;
    }

    /**
     * {@inheritDoc}
     */
    public function isRequired()
    {
        if (is_null($this->parent) || $this->parent->isRequired()) {
            return $this->required;
        } else {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isDisabled()
    {
        if (is_null($this->parent) || !$this->parent->isDisabled()) {
            return $this->getOption('disabled');
        } else {
            return true;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setGenerator(HtmlGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    /**
     * {@inheritDoc}
     */
    public function isMultipart()
    {
        return false;
    }

    /**
     * Returns true if the widget is hidden.
     *
     * @return Boolean true if the widget is hidden, false otherwise
     */
    public function isHidden()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function setParent(FieldInterface $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Returns the parent field.
     *
     * @return FieldInterface  The parent field
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Updates the field with default data
     *
     * @see FieldInterface
     */
    public function setData($data)
    {
        $this->data = $data;
        $this->transformedData = $this->transform($data);
    }

    /**
     * Binds POST data to the field, transforms and validates it.
     *
     * @param  string|array $taintedData  The POST data
     * @return boolean                    Whether the form is valid
     * @throws AlreadyBoundException      when the field is already bound
     */
    public function bind($taintedData)
    {
        $this->transformedData = is_array($taintedData) || is_object($taintedData) ? $taintedData : (string)$taintedData;
        $this->bound = true;
        $this->errors = array();

        if (is_string($this->transformedData) && $this->getOption('trim')) {
            $this->transformedData = trim($this->transformedData);
        }

        try {
            $this->data = $this->processData($data = $this->reverseTransform($this->transformedData));
            $this->transformedData = $this->transform($this->data);
        } catch (TransformationFailedException $e) {
            // TODO better text
            // TESTME
            $this->addError('invalid (localized)');
        }
    }

    /**
     * Processes the bound reverse-transformed data.
     *
     * This method can be overridden if you want to modify the data entered
     * by the user. Note that the data is already in reverse transformed format.
     *
     * This method will not be called if reverse transformation fails.
     *
     * @param  mixed $data
     * @return mixed
     */
    protected function processData($data)
    {
        return $data;
    }

    /**
     * Returns the normalized data of the field.
     *
     * @return mixed  When the field is not bound, the default data is returned.
     *                When the field is bound, the normalized bound data is
     *                returned if the field is valid, null otherwise.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Adds an error to the field.
     *
     * @see FieldInterface
     */
    public function addError($message, PropertyPath $path = null, $type = null)
    {
        $this->errors[] = $message;
    }

    /**
     * Returns whether the field is bound.
     *
     * @return boolean  true if the form is bound to input values, false otherwise
     */
    public function isBound()
    {
        return $this->bound;
    }

    /**
     * Returns whether the field is valid.
     *
     * @return boolean
     */
    public function isValid()
    {
        return $this->isBound() ? count($this->errors)==0 : false; // TESTME
    }

    /**
     * Returns weather there are errors.
     *
     * @return boolean  true if form is bound and not valid
     */
    public function hasErrors()
    {
        return $this->isBound() && !$this->isValid();
    }

    /**
     * Returns all errors
     *
     * @return array  An array of errors that occured during binding
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Sets the locale of this field.
     *
     * @see Localizable
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        if ($this->valueTransformer !== null && $this->valueTransformer instanceof Localizable) {
            $this->valueTransformer->setLocale($locale);
        }
    }

    /**
     * Sets the translator of this field.
     *
     * @see Translatable
     */
    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;

        if ($this->valueTransformer !== null && $this->valueTransformer instanceof Translatable) {
            $this->valueTransformer->setTranslator($translator);
        }
    }

    /**
     * Translates the text using the associated translator, if available
     *
     * If no translator is available, the original text is returned without
     * modification.
     *
     * @param  string $text         The text to translate
     * @param  array $parameters    The parameters to insert in the text
     * @return string               The translated text
     */
    protected function translate($text, array $parameters = array())
    {
        if ($this->translator !== null) {
            $text = $this->translator->translate($text, $parameters);
        }

        return $text;
    }

    /**
     * Injects the locale and the translator into the given object, if set.
     *
     * The locale is injected only if the object implements Localizable. The
     * translator is injected only if the object implements Translatable.
     *
     * @param object $object
     */
    protected function injectLocaleAndTranslator($object)
    {
        if ($object instanceof Localizable) {
            $object->setLocale($this->locale);
        }

        if (!is_null($this->translator) && $object instanceof Translatable) {
            $object->setTranslator($this->translator);
        }
    }

    /**
     * Sets the ValueTransformer.
     *
     * @param ValueTransformerInterface $valueTransformer
     */
    public function setValueTransformer(ValueTransformerInterface $valueTransformer)
    {
        $this->injectLocaleAndTranslator($valueTransformer);

        $this->valueTransformer = $valueTransformer;
    }

    /**
     * Returns the ValueTransformer.
     *
     * @return ValueTransformerInterface
     */
    public function getValueTransformer()
    {
        return $this->valueTransformer;
    }

    /**
     * Transforms the value if a value transformer is set.
     *
     * @param  mixed $value  The value to transform
     * @return string
     */
    protected function transform($value)
    {
        if ($value === null) {
            return '';
        } else if (null === $this->valueTransformer) {
            return $value;
        } else {
            return $this->valueTransformer->transform($value);
        }
    }

    /**
     * Reverse transforms a value if a value transformer is set.
     *
     * @param  string $value  The value to reverse transform
     * @return mixed
     */
    protected function reverseTransform($value)
    {
        if ($value === '') {
            return null;
        } else if (null === $this->valueTransformer) {
            return $value;
        } else {
            return $this->valueTransformer->reverseTransform($value);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateFromObject(&$objectOrArray)
    {
        // TODO throw exception if not object or array
        if ($this->propertyPath !== null) {
            $this->propertyPath->rewind();
            $this->setData($this->readPropertyPath($objectOrArray, $this->propertyPath));
        } else {
            // pass object through if the property path is empty
            $this->setData($objectOrArray);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function updateObject(&$objectOrArray)
    {
        // TODO throw exception if not object or array

        if ($this->propertyPath !== null) {
            $this->propertyPath->rewind();
            $this->updatePropertyPath($objectOrArray, $this->propertyPath);
        }
    }

    /**
     * Recursively reads the value of the property path in the data
     *
     * @param array|object $objectOrArray  An object or array
     * @param PropertyPath $propertyPath   A property path pointing to a property
     *                                     in the object/array.
     */
    protected function readPropertyPath(&$objectOrArray, PropertyPath $propertyPath)
    {
        if (is_object($objectOrArray)) {
            $value = $this->readProperty($objectOrArray, $propertyPath);
        }
        // arrays need to be treated separately (due to PHP bug?)
        // http://bugs.php.net/bug.php?id=52133
        else {
            if (!array_key_exists($propertyPath->getCurrent(), $objectOrArray)) {
                $objectOrArray[$propertyPath->getCurrent()] = array();
            }

            $value =& $objectOrArray[$propertyPath->getCurrent()];
        }

        if ($propertyPath->hasNext()) {
            $propertyPath->next();

            return $this->readPropertyPath($value, $propertyPath);
        } else {
            return $value;
        }
    }

    protected function updatePropertyPath(&$objectOrArray, PropertyPath $propertyPath)
    {
        if ($propertyPath->hasNext()) {
            if (is_object($objectOrArray)) {
                $value = $this->readProperty($objectOrArray, $propertyPath);
            }
            // arrays need to be treated separately (due to PHP bug?)
            // http://bugs.php.net/bug.php?id=52133
            else {
                if (!array_key_exists($propertyPath->getCurrent(), $objectOrArray)) {
                    $objectOrArray[$propertyPath->getCurrent()] = array();
                }

                $value =& $objectOrArray[$propertyPath->getCurrent()];
            }

            $propertyPath->next();

            $this->updatePropertyPath($value, $propertyPath);
        } else {
            $this->updateProperty($objectOrArray, $propertyPath);
        }
    }

    /**
     * Reads a specific element of the given data
     *
     * If the data is an array, the value at index $element is returned.
     *
     * If the data is an object, either the result of get{$element}(),
     * is{$element}() or the property $element is returned. If none of these
     * is publicly available, an exception is thrown
     *
     * @param  object $object  The data to read
     * @param  string $element              The element to read from the data
     * @return mixed                        The value of the element
     */
    protected function readProperty($object, PropertyPath $propertyPath)
    {
        if ($propertyPath->isIndex()) {
            if (!$object instanceof \ArrayAccess) {
                throw new InvalidPropertyException(sprintf('Index "%s" cannot be read from object of type "%s" because it doesn\'t implement \ArrayAccess', $propertyPath->getCurrent(), get_class($object)));
            }

            return $object[$propertyPath->getCurrent()];
        } else {
            $reflClass = new \ReflectionClass($object);
            $getter = 'get'.ucfirst($propertyPath->getCurrent());
            $isser = 'is'.ucfirst($propertyPath->getCurrent());
            $property = $propertyPath->getCurrent();

            if ($reflClass->hasMethod($getter)) {
                if (!$reflClass->getMethod($getter)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $getter, $reflClass->getName()));
                }

                return $object->$getter();
            } else if ($reflClass->hasMethod($isser)) {
                if (!$reflClass->getMethod($isser)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $isser, $reflClass->getName()));
                }

                return $object->$isser();
            } else if ($reflClass->hasProperty($property)) {
                if (!$reflClass->getProperty($property)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s". Maybe you should create the method "get%s()" or "is%s()"?', $property, $reflClass->getName(), ucfirst($property), ucfirst($property)));
                }

                return $object->$property;
            } else {
                throw new InvalidPropertyException(sprintf('Neither property "%s" nor method "%s()" nor method "%s()" exists in class "%s"', $property, $getter, $isser, $reflClass->getName()));
            }
        }
    }

    protected function updateProperty(&$objectOrArray, PropertyPath $propertyPath)
    {
        if (is_object($objectOrArray) && $propertyPath->isIndex()) {
            if (!$objectOrArray instanceof \ArrayAccess) {
                throw new InvalidPropertyException(sprintf('Index "%s" cannot be modified in object of type "%s" because it doesn\'t implement \ArrayAccess', $propertyPath->getCurrent(), get_class($objectOrArray)));
            }

            $objectOrArray[$propertyPath->getCurrent()] = $this->getData();
        } else if (is_object($objectOrArray)) {
            $reflClass = new \ReflectionClass($objectOrArray);
            $setter = 'set'.ucfirst($propertyPath->getCurrent());
            $property = $propertyPath->getCurrent();

            if ($reflClass->hasMethod($setter)) {
                if (!$reflClass->getMethod($setter)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Method "%s()" is not public in class "%s"', $setter, $reflClass->getName()));
                }

                $objectOrArray->$setter($this->getData());
            } else if ($reflClass->hasProperty($property)) {
                if (!$reflClass->getProperty($property)->isPublic()) {
                    throw new PropertyAccessDeniedException(sprintf('Property "%s" is not public in class "%s". Maybe you should create the method "set%s()"?', $property, $reflClass->getName(), ucfirst($property)));
                }

                $objectOrArray->$property = $this->getData();
            } else {
                throw new InvalidPropertyException(sprintf('Neither element "%s" nor method "%s()" exists in class "%s"', $property, $setter, $reflClass->getName()));
            }
        } else {
            $objectOrArray[$propertyPath->getCurrent()] = $this->getData();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function renderErrors()
    {
        $html = '';

        if ($this->hasErrors()) {
            $html .= "<ul>\n";

            foreach ($this->getErrors() as $error) {
                $html .= "<li>" . $error . "</li>\n";
            }

            $html .= "</ul>\n";
        }

        return $html;
    }
}