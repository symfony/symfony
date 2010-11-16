<?php

namespace Symfony\Component\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Contains the properties of a constraint definition.
 *
 * A constraint can be defined on a class, an option or a getter method.
 * The Constraint class encapsulates all the configuration required for
 * validating this class, option or getter result successfully.
 *
 * Constraint instances are immutable and serializable.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
abstract class Constraint
{
    const DEFAULT_GROUP = 'Default';

    public $groups = array(self::DEFAULT_GROUP);

    /**
     * Initializes the constraint with options.
     *
     * You should pass an associative array. The keys should be the names of
     * existing properties in this class. The values should be the value for these
     * properties.
     *
     * Alternatively you can override the method defaultOption() to return the
     * name of an existing property. If no associative array is passed, this
     * property is set instead.
     *
     * You can force that certain options are set by overriding
     * requiredOptions() to return the names of these options. If any
     * option is not set here, an exception is thrown.
     *
     * @param mixed $options The options (as associative array)
     *                       or the value for the default
     *                       option (any other type)
     *
     * @throws InvalidOptionsException       When you pass the names of non-existing
     *                                       options
     * @throws MissingOptionsException       When you don't pass any of the options
     *                                       returned by requiredOptions()
     * @throws ConstraintDefinitionException When you don't pass an associative
     *                                       array, but defaultOption() returns
     *                                       NULL
     */
    public function __construct($options = null)
    {
        $invalidOptions = array();
        $missingOptions = array_flip((array)$this->requiredOptions());

        if (is_array($options) && count($options) == 1 && isset($options['value'])) {
            $options = $options['value'];
        }

        if (is_array($options) && count($options) > 0 && is_string(key($options))) {
            foreach ($options as $option => $value) {
                if (property_exists($this, $option)) {
                    $this->$option = $value;
                    unset($missingOptions[$option]);
                } else {
                    $invalidOptions[] = $option;
                }
            }
        } else if ($options !== null && ! (is_array($options) && count($options) === 0)) {
            $option = $this->defaultOption();

            if (null === $option) {
                throw new ConstraintDefinitionException(
                    sprintf('No default option is configured for constraint %s', get_class($this))
                );
            }

            if (property_exists($this, $option)) {
                $this->$option = $options;
                unset($missingOptions[$option]);
            } else {
                $invalidOptions[] = $option;
            }
        }

        if (count($invalidOptions) > 0) {
            throw new InvalidOptionsException(
                sprintf('The options "%s" do not exist in constraint %s', implode('", "', $invalidOptions), get_class($this)),
                $invalidOptions
            );
        }

        if (count($missingOptions) > 0) {
            throw new MissingOptionsException(
                sprintf('The options "%s" must be set for constraint %s', implode('", "', array_keys($missingOptions)), get_class($this)),
                array_keys($missingOptions)
            );
        }

        $this->groups = (array)$this->groups;
    }

    /**
     * Unsupported operation.
     */
    public function __set($option, $value)
    {
        throw new InvalidOptionsException(sprintf('The option "%s" does not exist in constraint %s', $option, get_class($this)), array($option));
    }

    /**
     * Adds the given group if this constraint is in the Default group
     *
     * @param string $group
     */
    public function addImplicitGroupName($group)
    {
        if (in_array(Constraint::DEFAULT_GROUP, $this->groups) && !in_array($group, $this->groups)) {
            $this->groups[] = $group;
        }
    }

    /**
     * Returns the name of the default option
     *
     * Override this method to define a default option.
     *
     * @return string
     * @see __construct()
     */
    public function defaultOption()
    {
        return null;
    }

    /**
     * Returns the name of the required options
     *
     * Override this method if you want to define required options.
     *
     * @return array
     * @see __construct()
     */
    public function requiredOptions()
    {
        return array();
    }

    /**
     * Returns the name of the class that validates this constraint
     *
     * By default, this is the fully qualified name of the constraint class
     * suffixed with "Validator". You can override this method to change that
     * behaviour.
     *
     * @return string
     */
    public function validatedBy()
    {
        return get_class($this) . 'Validator';
    }
}