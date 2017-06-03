<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Configuration;

/**
 * The ParamConverter class handles the ParamConverter annotation parts.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @Annotation
 */
class ParamConverter extends ConfigurationAnnotation
{
    /**
     * The parameter name.
     *
     * @var string
     */
    protected $name;

    /**
     * The parameter class.
     *
     * @var string
     */
    protected $class;

    /**
     * An array of options.
     *
     * @var array
     */
    protected $options = array();

    /**
     * Whether or not the parameter is optional.
     *
     * @var bool
     */
    protected $optional = false;

    /**
     * Use explicitly named converter instead of iterating by priorities.
     *
     * @var string
     */
    protected $converter;

    /**
     * Returns the parameter name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the parameter name.
     *
     * @param string $name The parameter name
     */
    public function setValue($name)
    {
        $this->setName($name);
    }

    /**
     * Sets the parameter name.
     *
     * @param string $name The parameter name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the parameter class name.
     *
     * @return string $name
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Sets the parameter class name.
     *
     * @param string $class The parameter class name
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * Returns an array of options.
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Sets an array of options.
     *
     * @param array $options An array of options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    /**
     * Sets whether or not the parameter is optional.
     *
     * @param bool $optional Whether the parameter is optional
     */
    public function setIsOptional($optional)
    {
        $this->optional = (bool) $optional;
    }

    /**
     * Returns whether or not the parameter is optional.
     *
     * @return bool
     */
    public function isOptional()
    {
        return $this->optional;
    }

    /**
     * Get explicit converter name.
     *
     * @return string
     */
    public function getConverter()
    {
        return $this->converter;
    }

    /**
     * Set explicit converter name.
     *
     * @param string $converter
     */
    public function setConverter($converter)
    {
        $this->converter = $converter;
    }

    /**
     * Returns the annotation alias name.
     *
     * @return string
     *
     * @see ConfigurationInterface
     */
    public function getAliasName()
    {
        return 'converters';
    }

    /**
     * Multiple ParamConverters are allowed.
     *
     * @return bool
     *
     * @see ConfigurationInterface
     */
    public function allowArray()
    {
        return true;
    }
}
