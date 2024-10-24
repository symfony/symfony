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

use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\IsbnValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @see https://en.wikipedia.org/wiki/Isbn
 */
class IsbnValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): IsbnValidator
    {
        return new IsbnValidator();
    }

    public static function getValidIsbn10()
    {
        return [
            ['2723442284'],
            ['2723442276'],
            ['2723455041'],
            ['2070546810'],
            ['2711858839'],
            ['2756406767'],
            ['2870971648'],
            ['226623854X'],
            ['2851806424'],
            ['0321812700'],
            ['0-45122-5244'],
            ['0-4712-92311'],
            ['0-9752298-0-X'],
        ];
    }

    public static function getInvalidIsbn10()
    {
        return [
            ['27234422841', Isbn::TOO_LONG_ERROR],
            ['272344228', Isbn::TOO_SHORT_ERROR],
            ['0-4712-9231', Isbn::TOO_SHORT_ERROR],
            ['1234567890', Isbn::CHECKSUM_FAILED_ERROR],
            ['0987656789', Isbn::CHECKSUM_FAILED_ERROR],
            ['7-35622-5444', Isbn::CHECKSUM_FAILED_ERROR],
            ['0-4X19-92611', Isbn::CHECKSUM_FAILED_ERROR],
            ['0_45122_5244', Isbn::INVALID_CHARACTERS_ERROR],
            ['2870#971#648', Isbn::INVALID_CHARACTERS_ERROR],
            ['0-9752298-0-x', Isbn::INVALID_CHARACTERS_ERROR],
            ['1A34567890', Isbn::INVALID_CHARACTERS_ERROR],
            // chr(1) evaluates to 0
            // 2070546810 is valid
            ['2'.\chr(1).'70546810', Isbn::INVALID_CHARACTERS_ERROR],
        ];
    }

    public static function getValidIsbn13()
    {
        return [
            ['978-2723442282'],
            ['978-2723442275'],
            ['978-2723455046'],
            ['978-2070546817'],
            ['978-2711858835'],
            ['978-2756406763'],
            ['978-2870971642'],
            ['978-2266238540'],
            ['978-2851806420'],
            ['978-0321812704'],
            ['978-0451225245'],
            ['978-0471292319'],
        ];
    }

    public static function getInvalidIsbn13()
    {
        return [
            ['978-27234422821', Isbn::TOO_LONG_ERROR],
            ['978-272344228', Isbn::TOO_SHORT_ERROR],
            ['978-2723442-82', Isbn::TOO_SHORT_ERROR],
            ['978-2723442281', Isbn::CHECKSUM_FAILED_ERROR],
            ['978-0321513774', Isbn::CHECKSUM_FAILED_ERROR],
            ['979-0431225385', Isbn::CHECKSUM_FAILED_ERROR],
            ['980-0474292319', Isbn::CHECKSUM_FAILED_ERROR],
            ['0-4X19-92619812', Isbn::INVALID_CHARACTERS_ERROR],
            ['978_2723442282', Isbn::INVALID_CHARACTERS_ERROR],
            ['978#2723442282', Isbn::INVALID_CHARACTERS_ERROR],
            ['978-272C442282', Isbn::INVALID_CHARACTERS_ERROR],
            // chr(1) evaluates to 0
            // 978-2070546817 is valid
            ['978-2'.\chr(1).'70546817', Isbn::INVALID_CHARACTERS_ERROR],
        ];
    }

    public static function getValidIsbn()
    {
        return array_merge(
            self::getValidIsbn10(),
            self::getValidIsbn13()
        );
    }

    public static function getInvalidIsbn()
    {
        return array_merge(
            self::getInvalidIsbn10(),
            self::getInvalidIsbn13()
        );
    }

    public function testNullIsValid()
    {
        $constraint = new Isbn(true);

        $this->validator->validate(null, $constraint);

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $constraint = new Isbn(true);

        $this->validator->validate('', $constraint);

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $constraint = new Isbn(true);

        $this->validator->validate(new \stdClass(), $constraint);
    }

    /**
     * @dataProvider getValidIsbn10
     */
    public function testValidIsbn10($isbn)
    {
        $constraint = new Isbn(type: 'isbn10');

        $this->validator->validate($isbn, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     * @dataProvider getInvalidIsbn10
     */
    public function testInvalidIsbn10($isbn, $code)
    {
        $constraint = new Isbn([
            'type' => 'isbn10',
            'isbn10Message' => 'myMessage',
        ]);

        $this->validator->validate($isbn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isbn.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public function testInvalidIsbn10Named()
    {
        $this->validator->validate(
            '978-2723442282',
            new Isbn(type: Isbn::ISBN_10, isbn10Message: 'myMessage')
        );

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"978-2723442282"')
            ->setCode(Isbn::TOO_LONG_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidIsbn13
     */
    public function testValidIsbn13($isbn)
    {
        $constraint = new Isbn(type: 'isbn13');

        $this->validator->validate($isbn, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @group legacy
     * @dataProvider getInvalidIsbn13
     */
    public function testInvalidIsbn13($isbn, $code)
    {
        $constraint = new Isbn([
            'type' => 'isbn13',
            'isbn13Message' => 'myMessage',
        ]);

        $this->validator->validate($isbn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isbn.'"')
            ->setCode($code)
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidIsbn13
     */
    public function testInvalidIsbn13Named($isbn, $code)
    {
        $constraint = new Isbn(
            type: Isbn::ISBN_13,
            isbn13Message: 'myMessage',
        );

        $this->validator->validate($isbn, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isbn.'"')
            ->setCode($code)
            ->assertRaised();
    }

    /**
     * @dataProvider getValidIsbn
     */
    public function testValidIsbnAny($isbn)
    {
        $constraint = new Isbn();

        $this->validator->validate($isbn, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidIsbn10
     */
    public function testInvalidIsbnAnyIsbn10($isbn, $code)
    {
        $constraint = new Isbn(bothIsbnMessage: 'myMessage');

        $this->validator->validate($isbn, $constraint);

        // Too long for an ISBN-10, but not long enough for an ISBN-13
        if (Isbn::TOO_LONG_ERROR === $code) {
            $code = Isbn::TYPE_NOT_RECOGNIZED_ERROR;
        }

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isbn.'"')
            ->setCode($code)
            ->assertRaised();
    }

    /**
     * @dataProvider getInvalidIsbn13
     */
    public function testInvalidIsbnAnyIsbn13($isbn, $code)
    {
        $constraint = new Isbn(bothIsbnMessage: 'myMessage');

        $this->validator->validate($isbn, $constraint);

        // Too short for an ISBN-13, but not short enough for an ISBN-10
        if (Isbn::TOO_SHORT_ERROR === $code) {
            $code = Isbn::TYPE_NOT_RECOGNIZED_ERROR;
        }

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$isbn.'"')
            ->setCode($code)
            ->assertRaised();
    }
}
