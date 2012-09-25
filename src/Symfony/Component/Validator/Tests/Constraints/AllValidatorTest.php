<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Min;
use Symfony\Component\Validator\Constraints\Max;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AllValidator;

class AllValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $walker;
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->walker = $this->getMock('Symfony\Component\Validator\GraphWalker', array(), array(), '', false);
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new AllValidator();
        $this->validator->initialize($this->context);

        $this->context->expects($this->any())
            ->method('getGraphWalker')
            ->will($this->returnValue($this->walker));
        $this->context->expects($this->any())
            ->method('getGroup')
            ->will($this->returnValue('MyGroup'));
        $this->context->expects($this->any())
            ->method('getPropertyPath')
            ->will($this->returnValue('foo.bar'));
    }

    protected function tearDown()
    {
        $this->validator = null;
        $this->walker = null;
        $this->context = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new All(new Min(4)));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate('foo.barbar', new All(new Min(4)));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkSingleConstraint($array)
    {
        $constraint = new Min(4);

        $i = 0;

        foreach ($array as $key => $value) {
            $this->walker->expects($this->at($i++))
                ->method('walkConstraint')
                ->with($constraint, $value, 'MyGroup', 'foo.bar['.$key.']');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($array, new All($constraint));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkMultipleConstraints($array)
    {
        $constraint1 = new Min(4);
        $constraint2 = new Max(6);

        $constraints = array($constraint1, $constraint2);
        $i = 0;

        foreach ($array as $key => $value) {
            $this->walker->expects($this->at($i++))
                ->method('walkConstraint')
                ->with($constraint1, $value, 'MyGroup', 'foo.bar['.$key.']');
            $this->walker->expects($this->at($i++))
                ->method('walkConstraint')
                ->with($constraint2, $value, 'MyGroup', 'foo.bar['.$key.']');
        }

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($array, new All($constraints));
    }

    public function getValidArguments()
    {
        return array(
            array(array(5, 6, 7)),
            array(new \ArrayObject(array(5, 6, 7))),
        );
    }
}
