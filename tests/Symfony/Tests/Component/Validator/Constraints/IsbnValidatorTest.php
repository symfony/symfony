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

use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\IsbnValidator;

class IsbnValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new IsbnValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Isbn()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Isbn()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Isbn());
    }

    /**
     * @dataProvider getValidIsbns
     */
    public function testValidIsbns($isbn)
    {
        $this->assertTrue($this->validator->isValid($isbn, new Isbn()));
    }

    public function getValidIsbns()
    {
        return array(
            array(9783868830910),
            array('978-3589241323'),
            array('3-680-08783-7'),
            array('3 589 24132 2'),
            array('386883091X'),
            array(3866801920),
        );
    }

    /**
     * @dataProvider getInvalidIsbns
     */
    public function testInvalidIsbns($isbn)
    {
        $this->assertFalse($this->validator->isValid($isbn, new Isbn()));
    }

    public function getInvalidIsbns()
    {
        return array(
            array('978-3768830910'),
            array('386882091X'),
            array('srt')
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Isbn(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
        ));
    }
}
