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
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class ConstraintValidatorFactory implements ConstraintValidatorFactoryInterface
{
    protected $container;
    protected $validators = array();

    /**
     * Constructor.
     *
     * @param ContainerInterface $container The service container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Loads ids for services tagged as constraint validators.
     *
     * @param TaggedContainerInterface $container The tagged service container
     */
    public function loadTaggedServiceIds(TaggedContainerInterface $container)
    {
        foreach ($container->findTaggedServiceIds('validator.constraint_validator') as $id => $attributes) {
            if (isset($attributes[0]['alias'])) {
                $this->validators[$attributes[0]['alias']] = $id;
            }
        }
    }

    /**
     * Returns the validator for the supplied constraint.
     *
     * @param Constraint $constraint A constraint
     *
     * @return Symfony\Component\Validator\ConstraintValidator A validator for the supplied constraint
     *
     * @throws InvalidArgumentException If no validator for the supplied constraint is found
     */
    public function getInstance(Constraint $constraint)
    {
        $name = $constraint->validatedBy();

        if (!isset($this->validators[$name])) {
            throw new \InvalidArgumentException(sprintf('There is no "%s" constraint validator.', $name));
        }

        if (is_string($this->validators[$name])) {
            $this->validators[$name] = $this->container->get($this->validators[$name]);
        }

        return $this->validators[$name];
    }
}
