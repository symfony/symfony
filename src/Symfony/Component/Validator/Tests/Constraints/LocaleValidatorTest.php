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
        $this->validator->validate(null, new Locale(['canonicalize' => true]));

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
        $this->validator->validate('', new Locale(['canonicalize' => true]));

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedValueException
     */
    public function testLegacyExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Locale());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedValueException
     */
    public function testExpectsStringCompatibleType()
    {
        $this->validator->validate(new \stdClass(), new Locale(['canonicalize' => true]));
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
        return [
            ['en', ['canonicalize' => true]],
            ['en_US', ['canonicalize' => true]],
            ['pt', ['canonicalize' => true]],
            ['pt_PT', ['canonicalize' => true]],
            ['zh_Hans', ['canonicalize' => true]],
            ['fil_PH', ['canonicalize' => true]],
        ];
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     * @dataProvider getLegacyInvalidLocales
     */
    public function testLegacyInvalidLocales(string $locale)
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

    public function getLegacyInvalidLocales()
    {
        return [
            ['EN'],
            ['foobar'],
        ];
    }

    /**
     * @dataProvider getInvalidLocales
     */
    public function testInvalidLocales($locale)
    {
        $constraint = new Locale([
            'message' => 'myMessage',
            'canonicalize' => true,
        ]);

        $this->validator->validate($locale, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$locale.'"')
            ->setCode(Locale::NO_SUCH_LOCALE_ERROR)
            ->assertRaised();
    }

    public function getInvalidLocales()
    {
        return [
            ['baz'],
            ['foobar'],
        ];
    }

    /**
     * @group legacy
     * @expectedDeprecation The "canonicalize" option with value "false" is deprecated since Symfony 4.1, set it to "true" instead.
     * @dataProvider getUncanonicalizedLocales
     */
    public function testInvalidLocalesWithoutCanonicalization(string $locale)
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

    /**
     * @dataProvider getUncanonicalizedLocales
     */
    public function testValidLocalesWithCanonicalization(string $locale)
    {
        $constraint = new Locale([
            'message' => 'myMessage',
            'canonicalize' => true,
        ]);

        $this->validator->validate($locale, $constraint);

        $this->assertNoViolation();
    }

    public function getUncanonicalizedLocales(): iterable
    {
        return [
            ['en-US'],
            ['es-AR'],
            ['fr_FR.utf8'],
        ];
    }
}
