<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\ConstraintProviderInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class DynamicConstraintsEntity implements ConstraintProviderInterface
{
    protected $validationEnabled = false;
    protected $firstValue;
    
    public function setValidation($enabled)
    {
        $this->validationEnabled = $enabled;
    }
    
    public function getSecondValue() 
    {
        return null;
    }
    
    public function getConstraints(ClassMetadata $metadata)
    {
        if ($this->validationEnabled) {
            $metadata->addPropertyConstraint('firstValue', new FailingConstraint());
            $metadata->addGetterConstraint('secondValue', new FailingConstraint());
        }
    }
}
