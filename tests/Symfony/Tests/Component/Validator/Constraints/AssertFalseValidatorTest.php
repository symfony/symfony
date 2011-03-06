<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator;

use Symfony\Component\Validator\Constraints\AssertFalse;
use Symfony\Component\Validator\Constraints\AssertFalseValidator;

class AssertFalseValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new AssertFalseValidator();
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new AssertFalse()));
    }

    public function testFalseIsValid()
    {
        $this->assertTrue($this->validator->isValid(false, new AssertFalse()));
    }

    public function testTrueIsInvalid()
    {
        $constraint = new AssertFalse(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid(true, $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array());
    }
}