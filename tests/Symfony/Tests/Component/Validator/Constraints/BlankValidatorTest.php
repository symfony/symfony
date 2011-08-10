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

use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\BlankValidator;

class BlankValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new BlankValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Blank()));
    }

    public function testBlankIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Blank()));
    }

    /**
     * @dataProvider getInvalidValues
     */
    public function testInvalidValues($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Blank()));
    }

    public function getInvalidValues()
    {
        return array(
            array('foobar'),
            array(0),
            array(false),
            array(1234),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Blank(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
        ));
    }
}
