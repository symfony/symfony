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
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Form\Event\DataEvent;
use Symfony\Component\Form\Event\FilterDataEvent;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Exception\MissingOptionsException;
use Symfony\Component\Form\Exception\AlreadyBoundException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Exception\DanglingFieldException;
use Symfony\Component\Form\Exception\FieldDefinitionException;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\DataTransformer\DataTransformerInterface;
use Symfony\Component\Form\DataMapper\DataMapperInterface;
use Symfony\Component\Form\DataValidator\DataValidatorInterface;
use Symfony\Component\Form\Renderer\RendererInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

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
    private $fields = array();

    /**
     * Contains the names of bound values who don't belong to any fields
     * @var array
     */
    private $extraFields = array();

    private $dataMapper;

    public function __construct($name, EventDispatcherInterface $dispatcher,
        RendererInterface $renderer, DataTransformerInterface $clientTransformer = null,
        DataTransformerInterface $normalizationTransformer = null,
        DataMapperInterface $dataMapper, DataValidatorInterface $dataValidator = null,
        $required = false, $disabled = false, array $attributes = array())
    {
        $dispatcher->addListener(array(
            Events::postSetData,
            Events::preBind,
            Events::filterSetData,
            Events::filterBoundDataFromClient,
        ), $this);

        $this->dataMapper = $dataMapper;

        parent::__construct($name, $dispatcher, $renderer, $clientTransformer,
            $normalizationTransformer, $dataValidator, $required, $disabled,
            $attributes);
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

    public function add(FieldInterface $field)
    {
        $this->fields[$field->getName()] = $field;

        $field->setParent($this);

        $data = $this->getClientData();

        if (!empty($data)) {
            $this->dataMapper->mapDataToField($data, $field);
        }
    }

    public function remove($name)
    {
        if (isset($this->fields[$name])) {
            $this->fields[$name]->setParent(null);

            unset($this->fields[$name]);
        }
    }

    /**
     * Returns whether a field with the given name exists.
     *
     * @param  string $name
     * @return Boolean
     */
    public function has($name)
    {
        return isset($this->fields[$name]);
    }

    /**
     * Returns the field with the given name.
     *
     * @param  string $name
     * @return FieldInterface
     */
    public function get($name)
    {
        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        }

        throw new \InvalidArgumentException(sprintf('Field "%s" does not exist.', $name));
    }

    public function postSetData(DataEvent $event)
    {
        $form = $event->getField();
        $data = $form->getClientData();

        $this->dataMapper->mapDataToForm($data, $form);
    }

    public function filterSetData(FilterDataEvent $event)
    {
        $field = $event->getField();

        if (null === $field->getClientTransformer() && null === $field->getNormTransformer()) {
            $data = $event->getData();

            if (empty($data)) {
                $event->setData($this->dataMapper->createEmptyData());
            }
        }
    }

    public function filterBoundDataFromClient(FilterDataEvent $event)
    {
        $data = $event->getData();

        if (!is_array($data)) {
            throw new UnexpectedTypeException($data, 'array');
        }

        foreach ($this->fields as $name => $field) {
            if (!isset($data[$name])) {
                $data[$name] = null;
            }
        }

        foreach ($data as $name => $value) {
            if ($this->has($name)) {
                $this->fields[$name]->bind($value);
            }
        }

        $data = $this->getClientData();

        $this->dataMapper->mapFormToData($this, $data);

        $event->setData($data);
    }

    public function preBind(DataEvent $event)
    {
        $this->extraFields = array();

        foreach ((array)$event->getData() as $name => $value) {
            if (!$this->has($name)) {
                $this->extraFields[] = $name;
            }
        }
    }

    /**
     * Returns whether this form was bound with extra fields
     *
     * @return Boolean
     */
    public function isBoundWithExtraFields()
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
     * Returns true if the field exists (implements the \ArrayAccess interface).
     *
     * @param string $name The name of the field
     *
     * @return Boolean true if the widget exists, false otherwise
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * Returns the form field associated with the name (implements the \ArrayAccess interface).
     *
     * @param string $name The offset of the value to get
     *
     * @return Field A form field instance
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * Throws an exception saying that values cannot be set (implements the \ArrayAccess interface).
     *
     * @param string $offset (ignored)
     * @param string $value (ignored)
     *
     * @throws \LogicException
     */
    public function offsetSet($name, $field)
    {
        throw new \BadMethodCallException('offsetSet() is not supported');
    }

    /**
     * Throws an exception saying that values cannot be unset (implements the \ArrayAccess interface).
     *
     * @param string $name
     *
     * @throws \LogicException
     */
    public function offsetUnset($name)
    {
        throw new \BadMethodCallException('offsetUnset() is not supported');
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
     * Returns whether the CSRF token is valid
     *
     * @return Boolean
     */
    public function isCsrfTokenValid()
    {
        if (!$this->isCsrfProtected()) {
            return true;
        } else {
            $token = $this->get($this->csrfFieldName)->getClientData();

            return $this->csrfProvider->isCsrfTokenValid(get_class($this), $token);
        }
    }

    /**
     * Binds a request to the form
     *
     * If the request was a POST request, the data is bound to the form,
     * transformed and written into the form data (an object or an array).
     * You can set the form data by passing it in the second parameter
     * of this method or by passing it in the "data" option of the form's
     * constructor.
     *
     * @param Request $request    The request to bind to the form
     * @param array|object $data  The data from which to read default values
     *                            and where to write bound values
     */
    public function bindRequest(Request $request)
    {
        // Store the bound data in case of a post request
        switch ($request->getMethod()) {
            case 'POST':
            case 'PUT':
                $data = array_replace_recursive(
                    $request->request->get($this->getName(), array()),
                    $request->files->get($this->getName(), array())
                );
                break;
            case 'GET':
                $data = $request->query->get($this->getName(), array());
                break;
            default:
                throw new FormException(sprintf('The request method "%s" is not supported', $request->getMethod()));
        }

        $this->bind($data);
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
     * Validates the data of this form
     *
     * This method is called automatically during the validation process.
     *
     * @param ExecutionContext $context  The current validation context
     */
    public function validateData(ExecutionContext $context)
    {
        if (is_object($this->getData()) || is_array($this->getData())) {
            $groups = $this->getAttribute('validation_groups');
            $field = $this;

            while (!$groups && $field->hasParent()) {
                $field = $field->getParent();
                $groups = $field->getAttribute('validation_groups');
            }

            if (null === $groups) {
                $groups = array(null);
            }

            $propertyPath = $context->getPropertyPath();
            $graphWalker = $context->getGraphWalker();

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
    public function isEmpty()
    {
        foreach ($this->fields as $field) {
            if (!$field->isEmpty()) {
                return false;
            }
        }

        return true;
    }
}
