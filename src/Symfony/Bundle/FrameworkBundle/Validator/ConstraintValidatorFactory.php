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

use Psr\Container\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ContainerConstraintValidatorFactory;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\ValidatorException;

@trigger_error(sprintf('The %s class is deprecated since version 3.3 and will be removed in 4.0. Use %s instead.', ConstraintValidatorFactory::class, ContainerConstraintValidatorFactory::class), E_USER_DEPRECATED);

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
 *
 * @deprecated since version 3.3
 */
class ConstraintValidatorFactory extends ContainerConstraintValidatorFactory
{
    protected $container;
    protected $validators;

    public function __construct(ContainerInterface $container, array $validators = array())
    {
        parent::__construct($container);

        $this->validators = $validators;
        $this->container = $container;
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
            return parent::getInstance($constraint);
        }

        if (is_string($this->validators[$name])) {
            $this->validators[$name] = $this->container->get($this->validators[$name]);
        }

        if (!$this->validators[$name] instanceof ConstraintValidatorInterface) {
            throw new UnexpectedTypeException($this->validators[$name], 'Symfony\Component\Validator\ConstraintValidatorInterface');
        }

        return $this->validators[$name];
    }
}
