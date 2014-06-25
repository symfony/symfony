<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Markus Malkusch <markus@malkusch.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\Blz;
use Symfony\Component\Validator\Constraints\BlzValidator;

class BlzValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new BlzValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate(null, new Blz());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate('', new Blz());
    }

    /**
     * @dataProvider getValidBlzs
     */
    public function testValidBlzs($blz)
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate($blz, new Blz());
    }

    public function getValidBlzs()
    {
        return array(
            array('70169464'),
            array('10000000'),
        );
    }

    /**
     * @dataProvider getInvalidBlzs
     */
    public function testInvalidBlzs($blz)
    {
        $constraint = new Blz(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $blz,
            ));

        $this->validator->validate($blz, $constraint);
    }

    public function getInvalidBlzs()
    {
        return array(
            array('12345'),
            array('XXX'),
        );
    }
}
