<?php

namespace Symfony\Tests\Component\Validator\Fixtures;

use Symfony\Component\Validator\Constraint;

class FailingConstraint extends Constraint
{
    public $message = '';
}