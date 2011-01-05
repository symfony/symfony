<?php

namespace Symfony\Bundle\FrameworkBundle\Validator;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\TaggedContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;

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
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class ConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    protected $container;
    protected $validators;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The service container
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
     * @return Symfony\Component\Validator\ConstraintValidator A validator for the supplied constraint
     */
    public function getInstance(Constraint $constraint)
    {
        $name = $constraint->validatedBy();

        if (!isset($this->validators[$name])) {
            $this->validators[$name] = new $name();
        } elseif (is_string($this->validators[$name])) {
            $this->validators[$name] = $this->container->get($this->validators[$name]);
        }

        return $this->validators[$name];
    }
}
