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
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class LocaleValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): LocaleValidator
    {
        return new LocaleValidator();
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

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
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

    public static function getValidLocales()
    {
        return [
            ['en'],
            ['en_US'],
            ['pt'],
            ['pt_PT'],
            ['zh_Hans'],
            ['tl_PH'],
            ['fil_PH'], // alias for "tl_PH"
        ];
    }

    /**
     * @dataProvider getInvalidLocales
     */
    public function testInvalidLocales($locale)
    {
        $constraint = new Locale([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($locale, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$locale.'"')
            ->setCode(Locale::NO_SUCH_LOCALE_ERROR)
            ->assertRaised();
    }

    public static function getInvalidLocales()
    {
        return [
            ['baz'],
            ['foobar'],
        ];
    }

    /**
     * @dataProvider getUncanonicalizedLocales
     */
    public function testValidLocalesWithCanonicalization(string $locale)
    {
        $constraint = new Locale([
            'message' => 'myMessage',
        ]);

        $this->validator->validate($locale, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getValidLocales
     */
    public function testValidLocalesWithoutCanonicalization(string $locale)
    {
        $constraint = new Locale([
            'message' => 'myMessage',
            'canonicalize' => false,
        ]);

        $this->validator->validate($locale, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getUncanonicalizedLocales
     */
    public function testInvalidLocalesWithoutCanonicalization(string $locale)
    {
        $constraint = new Locale([
            'message' => 'myMessage',
            'canonicalize' => false,
        ]);

        $this->validator->validate($locale, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$locale.'"')
            ->setCode(Locale::NO_SUCH_LOCALE_ERROR)
            ->assertRaised();
    }

    public function testInvalidLocaleWithoutCanonicalizationNamed()
    {
        $this->validator->validate(
            'en-US',
            new Locale(message: 'myMessage', canonicalize: false)
        );

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"en-US"')
            ->setCode(Locale::NO_SUCH_LOCALE_ERROR)
            ->assertRaised();
    }

    public static function getUncanonicalizedLocales(): iterable
    {
        return [
            ['en-US'],
            ['es-AR'],
            ['fr_FR.utf8'],
        ];
    }
}
