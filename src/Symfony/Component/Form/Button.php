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
 *
 * @since v2.3.0
 */
class Button implements \IteratorAggregate, FormInterface
{
    /**
     * @var FormInterface
     */
    private $parent;

    /**
     * @var FormConfigInterface
     */
    private $config;

    /**
     * @var Boolean
     */
    private $submitted = false;

    /**
     * Creates a new button from a form configuration.
     *
     * @param FormConfigInterface $config The button's configuration.
     *
     * @since v2.3.0
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
     * @return Boolean Always returns false.
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function setParent(FormInterface $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
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
     * @return Boolean Always returns false.
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
     */
    public function remove($name)
    {
        throw new BadMethodCallException('Buttons cannot have children.');
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function all()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getErrors()
    {
        return array();
    }

    /**
     * Unsupported method.
     *
     * This method should not be invoked.
     *
     * @param string $modelData
     *
     * @throws BadMethodCallException
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
     */
    public function getData()
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return null Always returns null.
     *
     * @since v2.3.0
     */
    public function getNormData()
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return null Always returns null.
     *
     * @since v2.3.0
     */
    public function getViewData()
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @return array Always returns an empty array.
     *
     * @since v2.3.0
     */
    public function getExtraData()
    {
        return array();
    }

    /**
     * Returns the button's configuration.
     *
     * @return FormConfigInterface The configuration.
     *
     * @since v2.3.0
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Returns whether the button is submitted.
     *
     * @return Boolean true if the button was submitted.
     *
     * @since v2.3.0
     */
    public function isSubmitted()
    {
        return $this->submitted;
    }

    /**
     * Returns the name by which the button is identified in forms.
     *
     * @return string The name of the button.
     *
     * @since v2.3.0
     */
    public function getName()
    {
        return $this->config->getName();
    }

    /**
     * Unsupported method.
     *
     * @return null Always returns null.
     *
     * @since v2.3.0
     */
    public function getPropertyPath()
    {
        return null;
    }

    /**
     * Unsupported method.
     *
     * @param FormError $error
     *
     * @throws BadMethodCallException
     *
     * @since v2.3.0
     */
    public function addError(FormError $error)
    {
        throw new BadMethodCallException('Buttons cannot have errors.');
    }

    /**
     * Unsupported method.
     *
     * @return Boolean Always returns true.
     *
     * @since v2.3.0
     */
    public function isValid()
    {
        return true;
    }

    /**
     * Unsupported method.
     *
     * @return Boolean Always returns false.
     *
     * @since v2.3.0
     */
    public function isRequired()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function isDisabled()
    {
        return $this->config->getDisabled();
    }

    /**
     * Unsupported method.
     *
     * @return Boolean Always returns true.
     *
     * @since v2.3.0
     */
    public function isEmpty()
    {
        return true;
    }

    /**
     * Unsupported method.
     *
     * @return Boolean Always returns true.
     *
     * @since v2.3.0
     */
    public function isSynchronized()
    {
        return true;
    }

    /**
     * Unsupported method.
     *
     * @throws BadMethodCallException
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
     */
    public function handleRequest($request = null)
    {
        throw new BadMethodCallException('Buttons cannot handle requests. Call handleRequest() on the root form instead.');
    }

    /**
     * Submits data to the button.
     *
     * @param null|string $submittedData The data.
     * @param Boolean     $clearMissing  Not used.
     *
     * @return Button The button instance
     *
     * @throws Exception\AlreadySubmittedException If the button has already been submitted.
     *
     * @since v2.3.0
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
     *
     * @since v2.3.0
     */
    public function getRoot()
    {
        return $this->parent ? $this->parent->getRoot() : $this;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function isRoot()
    {
        return null === $this->parent;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function createView(FormView $parent = null)
    {
        if (null === $parent && $this->parent) {
            $parent = $this->parent->createView();
        }

        return $this->config->getType()->createView($this, $parent);
    }

    /**
     * Unsupported method.
     *
     * @return integer Always returns 0.
     *
     * @since v2.3.0
     */
    public function count()
    {
        return 0;
    }

    /**
     * Unsupported method.
     *
     * @return \EmptyIterator Always returns an empty iterator.
     *
     * @since v2.3.0
     */
    public function getIterator()
    {
        return new \EmptyIterator();
    }
}
