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

use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Constraints\LocaleValidator;

class LocaleValidatorTest extends LocalizedTestCase
{
    protected $validator;

    protected function setUp()
    {
        parent::setUp();

        $this->validator = new LocaleValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new Locale()));
    }

    public function testEmptyStringIsValid()
    {
        $this->assertTrue($this->validator->isValid('', new Locale()));
    }

    public function testExpectsStringCompatibleType()
    {
        $this->setExpectedException('Symfony\Component\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid(new \stdClass(), new Locale());
    }

    /**
     * @dataProvider getValidLocales
     */
    public function testValidLocales($date)
    {
        $this->assertTrue($this->validator->isValid($date, new Locale()));
    }

    public function getValidLocales()
    {
        return array(
            array('en'),
            array('en_US'),
            array('my'),
            array('zh_Hans'),
        );
    }

    /**
     * @dataProvider getInvalidLocales
     */
    public function testInvalidLocales($date)
    {
        $this->assertFalse($this->validator->isValid($date, new Locale()));
    }

    public function getInvalidLocales()
    {
        return array(
            array('EN'),
            array('foobar'),
        );
    }

    public function testMessageIsSet()
    {
        $constraint = new Locale(array(
            'message' => 'myMessage'
        ));

        $this->assertFalse($this->validator->isValid('foobar', $constraint));
        $this->assertEquals($this->validator->getMessageTemplate(), 'myMessage');
        $this->assertEquals($this->validator->getMessageParameters(), array(
            '{{ value }}' => 'foobar',
        ));
    }
}
