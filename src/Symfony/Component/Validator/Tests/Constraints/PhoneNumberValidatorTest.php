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

use Symfony\Component\Validator\Constraints\PhoneNumber;
use Symfony\Component\Validator\Constraints\PhoneNumberValidator;

class PhoneNumberValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new PhoneNumberValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    /**
     * @dataProvider getValues
     */
    public function testValidValues($value)
    {
        $constraint = new PhoneNumber(array("region" => "FR"));

        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($value, $constraint);
    }

    public function getValues()
    {
        return array(
            array("+33454554345"),
            array("+33 4 54 55 43 45"),
            array("04 54 55 43 45"),
            array("04.54.55.43.45"),
            array("0454554345"),
        );
    }
}
