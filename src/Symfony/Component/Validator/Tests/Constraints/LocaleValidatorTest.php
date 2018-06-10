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

use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Constraints\LocaleValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class LocaleValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator()
    {
        return new LocaleValidator();
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     *
     * @dataProvider getValidLocales
     */
    public function testLegacyNullIsValid()
    {
        $this->validator->validate(null, new Locale());

        $this->assertNoViolation();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Locale(array('canonicalize' => true)));

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     *
     * @dataProvider getValidLocales
     */
    public function testLegacyEmptyStringIsValid()
    {
        $this->validator->validate('', new Locale());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Locale(array('canonicalize' => true)));

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testLegacyExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Locale());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Locale(array('canonicalize' => true)));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     *
     * @dataProvider getValidLocales
     */
    public function testLegacyValidLocales(string $locale)
    {
        $this->validator->validate($locale, new Locale());

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocales
     */
    public function testValidLocales($locale, array $options)
    {
        $this->validator->validate($locale, new Locale($options));

        $this->assertNoViolation();
    }

    public function getValidLocales()
    {
        return array(
            array('en', array('canonicalize' => true)),
            array('en_US', array('canonicalize' => true)),
            array('pt', array('canonicalize' => true)),
            array('pt_PT', array('canonicalize' => true)),
            array('zh_Hans', array('canonicalize' => true)),
            array('fil_PH', array('canonicalize' => true)),
        );
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     * @dataProvider getLegacyInvalidLocales
     */
    public function testLegacyInvalidLocales(string $locale)
    {
        $constraint = new Locale(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($locale, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$locale.'"')
            ->setCode(Locale::NO_SUCH_LOCALE_ERROR)
            ->assertRaised();
    }

    public function getLegacyInvalidLocales()
    {
        return array(
            array('EN'),
            array('foobar'),
        );
    }

    /**
     * @dataProvider getInvalidLocales
     */
    public function testInvalidLocales($locale)
    {
        $constraint = new Locale(array(
            'message' => 'myMessage',
            'canonicalize' => true,
        ));

        $this->validator->validate($locale, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$locale.'"')
            ->setCode(Locale::NO_SUCH_LOCALE_ERROR)
            ->assertRaised();
    }

    public function getInvalidLocales()
    {
        return array(
            array('baz'),
            array('foobar'),
        );
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     * @dataProvider getUncanonicalizedLocales
     */
    public function testInvalidLocalesWithoutCanonicalization(string $locale)
    {
        $constraint = new Locale(array(
            'message' => 'myMessage',
        ));

        $this->validator->validate($locale, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$locale.'"')
            ->setCode(Locale::NO_SUCH_LOCALE_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getUncanonicalizedLocales
     */
    public function testValidLocalesWithCanonicalization(string $locale)
    {
        $constraint = new Locale(array(
            'message' => 'myMessage',
            'canonicalize' => true,
        ));

        $this->validator->validate($locale, $constraint);

        $this->assertNoViolation();
    }

    public function getUncanonicalizedLocales(): iterable
    {
        return array(
            array('en-US'),
            array('es-AR'),
            array('fr_FR.utf8'),
        );
    }
}
