<?php

namespace Symfony\Components\Validator;

use Symfony\Components\DependencyInjection\ContainerInterface,
        Symfony\Components\Validator\ConstraintValidatorFactoryInterface,
        Symfony\Components\Validator\Constraint,
        Symfony\Components\Validator\ConstraintValidatorInterface;

class DependencyInjectionValidatorFactory implements ConstraintValidatorFactoryInterface
{

    protected $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Gets contraint validator service, setting it if it doesn't exist
     * Throws exception if validator service is not instance of ConstraintValidatorInterface
     * @param Constraint $constraint
     * @return ConstraintValidatorInterface
     * @throws \LogicException
     */
    public function getInstance(Constraint $constraint)
    {
        $className = $constraint->validatedBy();
        $id = $this->getServiceIdFromClass($className);

        if (!$this->container->hasService($id)) {
            $this->container->setService($id, new $className());
        }

        $validator = $this->container->getService($id);

        if (!$validator instanceof ConstraintValidatorInterface)  {
            throw new \LogicException('Service ' . $id . ' is not instance of ConstraintValidatorInterface');
        }

        return $validator;
    }

    /**
     * Gets service id, corresponding to full class name of ConstraintValidator
     * @param string $className
     * @return string
     */
    protected function getServiceIdFromClass($className)
    {
        return str_replace('\\', '.', $className);
    }
}