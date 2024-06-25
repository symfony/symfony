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

use Symfony\Component\Validator\Constraints\Ulid;
use Symfony\Component\Validator\Constraints\UlidValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Laurent Clouet <laurent35240@gmail.com>
 */
class UlidValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): UlidValidator
    {
        return new UlidValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Ulid());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Ulid());

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Ulid());
    }

    public function testValidUlid()
    {
        $this->validator->validate('01ARZ3NDEKTSV4RRFFQ69G5FAV', new Ulid());

        $this->assertNoViolation();
    }

    public function testValidUlidAsBase58()
    {
        $this->validator->validate('1CCD2w4mK2m455S2BAXFht', new Ulid(format: Ulid::FORMAT_BASE_58));

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidUlids
     */
    public function testInvalidUlid(string $ulid, string $code)
    {
        $constraint = new Ulid([
            'message' => 'testMessage',
        ]);

        $this->validator->validate($ulid, $constraint);

        $this->buildViolation('testMessage')
            ->setParameters([
                '{{ value }}' => '"'.$ulid.'"',
                '{{ format }}' => Ulid::FORMAT_BASE_32,
            ])
            ->setCode($code)
            ->assertRaised();
    }

    public static function getInvalidUlids(): array
    {
        return [
            ['01ARZ3NDEKTSV4RRFFQ69G5FA', Ulid::TOO_SHORT_ERROR],
            ['01ARZ3NDEKTSV4RRFFQ69G5FAVA', Ulid::TOO_LONG_ERROR],
            ['01ARZ3NDEKTSV4RRFFQ69G5FAO', Ulid::INVALID_CHARACTERS_ERROR],
            ['Z1ARZ3NDEKTSV4RRFFQ69G5FAV', Ulid::TOO_LARGE_ERROR],
            ['not-even-ulid-like', Ulid::TOO_SHORT_ERROR],
        ];
    }

    /**
     * @dataProvider getInvalidBase58Ulids
     */
    public function testInvalidBase58Ulid(string $ulid, string $code)
    {
        $constraint = new Ulid(message: 'testMessage', format: Ulid::FORMAT_BASE_58);

        $this->validator->validate($ulid, $constraint);

        $this->buildViolation('testMessage')
            ->setParameters([
                '{{ value }}' => '"'.$ulid.'"',
                '{{ format }}' => Ulid::FORMAT_BASE_58,
            ])
            ->setCode($code)
            ->assertRaised();
    }

    public static function getInvalidBase58Ulids(): array
    {
        return [
            ['1CCD2w4mK2m455S2BAXFh', Ulid::TOO_SHORT_ERROR],
            ['1CCD2w4mK2m455S2BAXFhttt', Ulid::TOO_LONG_ERROR],
            ['1CCD2w4mK2m455S2BAXFhO', Ulid::INVALID_CHARACTERS_ERROR],
            ['not-even-ulid-like', Ulid::TOO_SHORT_ERROR],
        ];
    }

    public function testInvalidUlidNamed()
    {
        $constraint = new Ulid(message: 'testMessage');

        $this->validator->validate('01ARZ3NDEKTSV4RRFFQ69G5FA', $constraint);

        $this->buildViolation('testMessage')
            ->setParameters([
                '{{ value }}' => '"01ARZ3NDEKTSV4RRFFQ69G5FA"',
                '{{ format }}' => Ulid::FORMAT_BASE_32,
            ])
            ->setCode(Ulid::TOO_SHORT_ERROR)
            ->assertRaised();
    }
}
