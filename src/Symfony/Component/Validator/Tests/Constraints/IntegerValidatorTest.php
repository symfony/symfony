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

use Symfony\Component\Validator\Constraints\Integer;
use Symfony\Component\Validator\Constraints\IntegerValidator;

/**
 * @author Elnur Abdurrakhimov <elnur@elnur.pro>
 */
class IntegerValidatorTest extends \PHPUnit_Framework_TestCase
{
    const MESSAGE = 'message';

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $context;

    /**
     * @var IntegerValidator
     */
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContextInterface');
        $this->validator = new IntegerValidator();
        $this->validator->initialize($this->context);
    }

    /**
     * @dataProvider validValues
     */
    public function testValidValues($value)
    {
        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($value, new Integer());
    }

    /**
     * @dataProvider invalidValues
     */
    public function testInvalidValues($value)
    {
        $this->context
            ->expects($this->once())
            ->method('addViolation')
            ->with(self::MESSAGE);

        $this->validator->validate($value, new Integer(array(
            'message' => self::MESSAGE,
        )));
    }

    public function testNullIsValid()
    {
        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Integer());
    }

    public function testEmptyStringIsValid()
    {
        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Integer());
    }

    /**
     * @return array
     */
    public function validValues()
    {
        return array(
            array(0),
            array('0'),
            array('-0'),
            array(-1),
            array('-1'),
            array(100500),
        );
    }

    /**
     * @return array
     */
    public function invalidValues()
    {
        return array(
            array(1.5),
            array('0.1'),
            array('blah'),
            array(new \DateTime()),
            array(false),
            array(true),
            array(array()),
            array(array('a')),
        );
    }
}
