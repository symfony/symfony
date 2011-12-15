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

use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\LanguageValidator;

class LanguageValidatorTest extends LocalizedTestCase
{
    protected $validator;

    protected function setUp()
    {
        parent::setUp();

        $this->validator = new LanguageValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Language()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Language()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Language());
    }

    /**
     * @dataProvider getValidLanguages
     */
    public function testValidLanguages($date)
    {
        $this->assertTrue($this->validator->isValid($date, new Language()));
    }

    public function getValidLanguages()
    {
        return array(
            array('en'),
            array('en_US'),
            array('my'),
        );
    }

    /**
     * @dataProvider getInvalidLanguages
     */
    public function testInvalidLanguages($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Language()));
    }

    public function getInvalidLanguages()
    {
        return array(
            array('EN'),
            array('foobar'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Language(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
        ));
    }
}
