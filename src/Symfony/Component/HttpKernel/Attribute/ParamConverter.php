<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

/**
 * The ParamConverter class handles the ParamConverter attribute parts.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class ParamConverter
{
    /**
     * The parameter name.
     *
     * @var string
     */
    private $name;

    /**
     * The parameter class.
     *
     * @var string
     */
    private $class;

    /**
     * An array of options.
     *
     * @var array
     */
    private $options = [];

    /**
     * Whether or not the parameter is optional.
     *
     * @var bool
     */
    private $isOptional = false;

    /**
     * Use explicitly named converter instead of iterating by priorities.
     *
     * @var string
     */
    private $converter;

    public function __construct(
        string $name,
        string $class = null,
        array $options = [],
        bool $isOptional = false,
        string $converter = null
    ) {
        $this->name = $name;
        $this->class = $class;
        $this->options = $options;
        $this->isOptional = $isOptional;
        $this->converter = $converter;
    }

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
        $this->isOptional = (bool) $optional;
    }

    /**
     * Returns whether or not the parameter is optional.
     *
     * @return bool
     */
    public function isOptional()
    {
        return $this->isOptional;
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
}
