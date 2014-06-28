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

use Symfony\Component\Validator\Constraints\Bic;
use Symfony\Component\Validator\Constraints\BicValidator;

/**
 * @large
 */
class BicDEValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new BicValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate(null, new Bic());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate('', new Bic());
    }

    /**
     * @dataProvider getValidBics
     */
    public function testValidBics($bic)
    {
        $this->context->expects($this->never())->method('addViolation');

        $this->validator->validate($bic, new Bic(Bic::DE));
    }

    public function getValidBics()
    {
        return array(
            array('VZVDDED1XXX'),
            array('VZVDDED1'),
        );
    }

    /**
     * @dataProvider getInvalidBics
     */
    public function testInvalidBics($bic)
    {
        $constraint = new Bic(array(
            'value' => Bic::DE,
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $bic,
            ));

        $this->validator->validate($bic, $constraint);
    }

    public function getInvalidBics()
    {
        return array(
            array('VZVDDED1~~~'),
        );
    }
}
