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

use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\LanguageValidator;

class LanguageValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $context;
    protected $validator;

    protected function setUp()
    {
        IntlTestHelper::requireFullIntl($this);

        $this->context = $this->getMock('Symfony\Component\Validator\ExecutionContext', array(), array(), '', false);
        $this->validator = new LanguageValidator();
        $this->validator->initialize($this->context);
    }

    protected function tearDown()
    {
        $this->context = null;
        $this->validator = null;
    }

    public function testNullIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(null, new Language());
    }

    public function testEmptyStringIsValid()
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate('', new Language());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Language());
    }

    /**
     * @dataProvider getValidLanguages
     */
    public function testValidLanguages($language)
    {
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($language, new Language());
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
    public function testInvalidLanguages($language)
    {
        $constraint = new Language(array(
            'message' => 'myMessage'
        ));

        $this->context->expects($this->once())
            ->method('addViolation')
            ->with('myMessage', array(
                '{{ value }}' => $language,
            ));

        $this->validator->validate($language, $constraint);
    }

    public function getInvalidLanguages()
    {
        return array(
            array('EN'),
            array('foobar'),
        );
    }

    public function testValidateUsingCountrySpecificLocale()
    {
        \Locale::setDefault('fr_FR');
        $existingLanguage = 'en';
        $this->context->expects($this->never())
            ->method('addViolation');

        $this->validator->validate($existingLanguage, new Language(array(
            'message' => 'aMessage'
        )));
    }
}
