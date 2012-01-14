<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\ConstraintProviderInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class DynamicConstraintsEntity implements ConstraintProviderInterface
{
    protected $firstValue;
    
    public function getSecondValue() {
        return null;
    }
    
    public function getConstraints(ClassMetadata $metadata)
    {
        $metadata->addPropertyConstraint('firstValue', new FailingConstraint());
        $metadata->addGetterConstraint('secondValue', new FailingConstraint());
    }
}
