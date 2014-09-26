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
use Symfony\Component\Form\Exception\BadMethodCallException;

/**
 * A form button.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Button implements \IteratorAggregate, FormInterface
{
    /**
     * @var FormInterface|null
     */
    private $parent;

    /**
     * @var FormConfigInterface
     */
    private $config;

    /**
     * @var bool
     */
    private $submitted = false;

    /**
     * Creates a new button from a form configuration.
     *
     * @param FormConfigInterface $config The button's configuration.
     */
    public function __construct(FormConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Unsupported method.
     *
     * @param mixed $offset
     *
     * @return bool Always returns false.
     */
    public function offsetExists($offset)
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $offset
     *
     * @throws BadMethodCallException
     */
    public function offsetGet($offset)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $offset
     * @param mixed $value
     *
     * @throws BadMethodCallException
     */
    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param mixed $offset
     *
     * @throws BadMethodCallException
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * {@inheritdoc}
     */
    public function setParent(FormInterface $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param int|string|FormInterface $child
     * @param null                     $type
     * @param array                    $options
     *
     * @throws BadMethodCallException
     */
    public function add($child, $type = null, array $options = array())
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param string $name
     *
     * @throws BadMethodCallException
     */
    public function get($name)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * Unsupported method.
     *
     * @param string $name
     *
     * @return bool Always returns false.
     */
    public function has($name)
    {
        return false;
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param string $name
     *
     * @throws BadMethodCallException
     */
    public function remove($name)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * {@inheritdoc}
     */
    public function all()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors($deep = false, $flatten = true)
    {
        return new FormErrorIterator($this, array());
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param string $modelData
     *
     * @throws BadMethodCallException
     */
    public function setData($modelData)
    {
        // called during initialization of the form tree
        // noop
    }

    /**
     * Unsupported method.
     *
     * @return null Always returns null.
     */
    public function getData()
    {
    }

    /**
     * Unsupported method.
     *
     * @return null Always returns null.
     */
    public function getNormData()
    {
    }

    /**
     * Unsupported method.
     *
     * @return null Always returns null.
     */
    public function getViewData()
    {
    }

    /**
     * Unsupported method.
     *
     * @return array Always returns an empty array.
     */
    public function getExtraData()
    {
        return array();
    }

    /**
     * Returns the button's configuration.
     *
     * @return FormConfigInterface The configuration.
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns whether the button is submitted.
     *
     * @return bool true if the button was submitted.
     */
    public function isSubmitted()
    {
        return $this->submitted;
    }

    /**
     * Returns the name by which the button is identified in forms.
     *
     * @return string The name of the button.
     */
    public function getName()
    {
        return $this->config->getName();
    }

    /**
     * Unsupported method.
     *
     * @return null Always returns null.
     */
    public function getPropertyPath()
    {
    }

    /**
     * Unsupported method.
     *
     * @param FormError $error
     *
     * @throws BadMethodCallException
     */
    public function addError(FormError $error)
    {
        throw new BadMethodCallException('Buttons cannot have errors.');
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns true.
     */
    public function isValid()
    {
        return true;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns false.
     */
    public function isRequired()
    {
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
     * Unsupported method.
     *
     * @return bool Always returns true.
     */
    public function isEmpty()
    {
        return true;
    }

    /**
     * Unsupported method.
     *
     * @return bool Always returns true.
     */
    public function isSynchronized()
    {
        return true;
    }

    /**
     * Unsupported method.
     *
     * @return null Always returns null
     */
    public function getTransformationFailure()
    {
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     */
    public function initialize()
    {
        throw new BadMethodCallException('Buttons cannot be initialized. Call initialize() on the root form instead.');
    }

    /**
     * Unsupported method.
     *
     * @param mixed $request
     *
     * @throws BadMethodCallException
     */
    public function handleRequest($request = null)
    {
        throw new BadMethodCallException('Buttons cannot handle requests. Call handleRequest() on the root form instead.');
    }

    /**
     * Submits data to the button.
     *
     * @param null|string $submittedData The data.
     * @param bool        $clearMissing  Not used.
     *
     * @return Button The button instance
     *
     * @throws Exception\AlreadySubmittedException If the button has already been submitted.
     */
    public function submit($submittedData, $clearMissing = true)
    {
        if ($this->submitted) {
            throw new AlreadySubmittedException('A form can only be submitted once');
        }

        $this->submitted = true;

        return $this;
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
    public function createView(FormView $parent = null)
    {
        if (null === $parent && $this->parent) {
            $parent = $this->parent->createView();
        }

        $type = $this->config->getType();
        $options = $this->config->getOptions();

        $view = $type->createView($this, $parent);

        $type->buildView($view, $this, $options);
        $type->finishView($view, $this, $options);

        return $view;
    }

    /**
     * Unsupported method.
     *
     * @return int Always returns 0.
     */
    public function count()
    {
        return 0;
    }

    /**
     * Unsupported method.
     *
     * @return \EmptyIterator Always returns an empty iterator.
     */
    public function getIterator()
    {
        return new \EmptyIterator();
    }
}
