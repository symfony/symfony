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
use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Constraints\LocaleValidator;

class LocaleValidatorTest extends AbstractConstraintValidatorTest
{
    protected function createValidator()
    {
        return new LocaleValidator();
    }

    protected function setUp()
    {
        IntlTestHelper::requireIntl($this);

        parent::setUp();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Locale());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Locale());

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Locale());
    }

    /**
     * @dataProvider getValidLocales
     */
    public function testValidLocales($locale)
    {
        $this->validator->validate($locale, new Locale());

        $this->assertNoViolation();
    }

    public function getValidLocales()
    {
        return array(
            array('en'),
            array('en_US'),
            array('pt'),
            array('pt_PT'),
            array('zh_Hans'),
        );
    }

    /**
     * @dataProvider getInvalidLocales
     */
    public function testInvalidLocales($locale)
    {
        $constraint = new Locale(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($locale, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$locale.'"')
            ->assertRaised();
    }

    public function getInvalidLocales()
    {
        return array(
            array('EN'),
            array('foobar'),
        );
    }
}
