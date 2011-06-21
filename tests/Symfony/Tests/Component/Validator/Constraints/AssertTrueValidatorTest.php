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

use Symfony\Component\Validator\Constraints\True;
use Symfony\Component\Validator\Constraints\TrueValidator;

class TrueValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new TrueValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new True()));
    }

    public function testTrueIsValid()
    {
        $this->assertTrue($this->validator->isValid(true, new True()));
    }

    public function testFalseIsInvalid()
    {
        $constraint = new True(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid(false, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array());
    }
}
