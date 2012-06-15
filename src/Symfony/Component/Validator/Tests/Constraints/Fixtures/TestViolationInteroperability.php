<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints\Fixtures;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\GlobalExecutionContext;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ExecutionContext;

class TestViolationInteroperability extends \PHPUnit_Framework_TestCase
{

    public static function newInstance(){
        return new self();
    }

    public function testViolation(Constraint $constraint, ConstraintValidator $validator, $value){
        $walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $globalContext = new GlobalExecutionContext('Root', $walker, $metadataFactory);
        $context = new ExecutionContext($globalContext, $value, 'foo.bar', 'Group', 'ClassName', 'propertyName');
        $validator->initialize($context);
        $validator->validate($value, $constraint);
        $foo = $context->getViolations();
        $this->assertNotEmpty($foo->count(), 'The violation count should not be empty.');
        $this->assertEquals($value, $foo[0]->getInvalidValue());
    }
}
