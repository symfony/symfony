<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraints\False;
use Symfony\Component\Validator\Constraints\FalseValidator;

class FalseValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new FalseValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new False()));
    }

    public function testFalseIsValid()
    {
        $this->assertTrue($this->validator->isValid(false, new False()));
    }

    public function testTrueIsInvalid()
    {
        $constraint = new False(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid(true, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array());
    }
}
