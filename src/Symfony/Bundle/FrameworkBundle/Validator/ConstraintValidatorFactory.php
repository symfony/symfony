<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Validator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\ValidatorException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Uses a service container to create constraint validators.
 *
 * A constraint validator should be tagged as "validator.constraint_validator"
 * in the service container and include an "alias" attribute:
 *
 *     <service id="some_doctrine_validator">
 *         <argument type="service" id="doctrine.orm.some_entity_manager" />
 *         <tag name="validator.constraint_validator" alias="some_alias" />
 *     </service>
 *
 * A constraint may then return this alias in its validatedBy() method:
 *
 *     public function validatedBy()
 *     {
 *         return 'some_alias';
 *     }
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class ConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    /**
     * @deprecated since version 3.3, to be removed in 4.0 alongside with magic methods below
     */
    protected $container;

    /**
     * @deprecated since version 3.3, to be removed in 4.0 alongside with magic methods below
     */
    protected $validators;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container  The service container
     * @param array              $validators An array of validators
     */
    public function __construct(ContainerInterface $container, array $validators = array())
    {
        $this->container = $container;
        $this->validators = $validators;
    }

    /**
     * Returns the validator for the supplied constraint.
     *
     * @param Constraint $constraint A constraint
     *
     * @return ConstraintValidatorInterface A validator for the supplied constraint
     *
     * @throws ValidatorException      When the validator class does not exist
     * @throws UnexpectedTypeException When the validator is not an instance of ConstraintValidatorInterface
     */
    public function getInstance(Constraint $constraint)
    {
        $name = $constraint->validatedBy();

        if (!isset($this->validators[$name])) {
            if (!class_exists($name)) {
                throw new ValidatorException(sprintf('Constraint validator "%s" does not exist or it is not enabled. Check the "validatedBy" method in your constraint class "%s".', $name, get_class($constraint)));
            }

            $this->validators[$name] = new $name();
        } elseif (is_string($this->validators[$name])) {
            $this->validators[$name] = $this->container->get($this->validators[$name]);
        }

        if (!$this->validators[$name] instanceof ConstraintValidatorInterface) {
            throw new UnexpectedTypeException($this->validators[$name], 'Symfony\Component\Validator\ConstraintValidatorInterface');
        }

        return $this->validators[$name];
    }

    /**
     * @internal
     */
    public function __get($name)
    {
        if ('validators' === $name || 'container' === $name) {
            @trigger_error(sprintf('Using the "%s::$%s" property is deprecated since version 3.3 as it will be removed/private in 4.0.', __CLASS__, $name), E_USER_DEPRECATED);
        }

        return $this->$name;
    }

    /**
     * @internal
     */
    public function __set($name, $value)
    {
        if ('validators' === $name || 'container' === $name) {
            @trigger_error(sprintf('Using the "%s::$%s" property is deprecated since version 3.3 as it will be removed/private in 4.0.', __CLASS__, $name), E_USER_DEPRECATED);
        }

        $this->$name = $value;
    }

    /**
     * @internal
     */
    public function __isset($name)
    {
        if ('validators' === $name || 'container' === $name) {
            @trigger_error(sprintf('Using the "%s::$%s" property is deprecated since version 3.3 as it will be removed/private in 4.0.', __CLASS__, $name), E_USER_DEPRECATED);
        }

        return isset($this->$name);
    }

    /**
     * @internal
     */
    public function __unset($name)
    {
        if ('validators' === $name || 'container' === $name) {
            @trigger_error(sprintf('Using the "%s::$%s" property is deprecated since version 3.3 as it will be removed/private in 4.0.', __CLASS__, $name), E_USER_DEPRECATED);
        }

        unset($this->$name);
    }
}
