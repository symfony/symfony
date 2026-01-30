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

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Intl\Util\IntlTestHelper;
use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\LanguageValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class LanguageValidatorTest extends ConstraintValidatorTestCase
{
    private string $defaultLocale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultLocale = \Locale::getDefault();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        \Locale::setDefault($this->defaultLocale);
    }

    protected function createValidator(): LanguageValidator
    {
        return new LanguageValidator();
    }

    public function testNullIsValid(): void
    {
        $this->validator->validate(null, new Language());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid(): void
    {
        $this->validator->validate('', new Language());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Language());
    }

    #[DataProvider('getValidLanguages')]
    public function testValidLanguages($language): void
    {
        $this->validator->validate($language, new Language());

        $this->assertNoViolation();
    }

    public static function getValidLanguages()
    {
        return [
            ['en'],
            ['my'],
        ];
    }

    #[DataProvider('getInvalidLanguages')]
    public function testInvalidLanguages($language): void
    {
        $constraint = new Language(message: 'myMessage');

        $this->validator->validate($language, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$language.'"')
            ->setCode(Language::NO_SUCH_LANGUAGE_ERROR)
            ->assertRaised();
    }

    public static function getInvalidLanguages()
    {
        return [
            ['EN'],
            ['foobar'],
        ];
    }

    #[DataProvider('getValidAlpha3Languages')]
    public function testValidAlpha3Languages($language): void
    {
        $this->validator->validate($language, new Language(alpha3: true));

        $this->assertNoViolation();
    }

    public static function getValidAlpha3Languages()
    {
        return [
            ['deu'],
            ['eng'],
            ['fra'],
        ];
    }

    #[DataProvider('getInvalidAlpha3Languages')]
    public function testInvalidAlpha3Languages($language): void
    {
        $constraint = new Language(
            alpha3: true,
            message: 'myMessage',
        );

        $this->validator->validate($language, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$language.'"')
            ->setCode(Language::NO_SUCH_LANGUAGE_ERROR)
            ->assertRaised();
    }

    public static function getInvalidAlpha3Languages()
    {
        return [
            ['foobar'],
            ['en'],
            ['ZZZ'],
            ['zzz'],
        ];
    }

    public function testInvalidAlpha3LanguageNamed(): void
    {
        $this->validator->validate(
            'DE',
            new Language(alpha3: true, message: 'myMessage')
        );

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"DE"')
            ->setCode(Language::NO_SUCH_LANGUAGE_ERROR)
            ->assertRaised();
    }

    public function testValidateUsingCountrySpecificLocale(): void
    {
        IntlTestHelper::requireFullIntl($this);

        \Locale::setDefault('fr_FR');
        $existingLanguage = 'en';

        $this->validator->validate($existingLanguage, new Language(message: 'aMessage'));

        $this->assertNoViolation();
    }
}
