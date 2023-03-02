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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Uuid;
use Symfony\Component\Validator\Constraints\UuidValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @author Colin O'Dell <colinodell@gmail.com>
 */
class UuidValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): UuidValidator
    {
        return new UuidValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Uuid());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Uuid());

        $this->assertNoViolation();
    }

    public function testExpectsUuidConstraintCompatibleType()
    {
        $this->expectException(UnexpectedTypeException::class);
        $constraint = $this->getMockForAbstractClass(Constraint::class);

        $this->validator->validate('216fff40-98d9-11e3-a5e2-0800200c9a66', $constraint);
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new Uuid());
    }

    /**
     * @dataProvider getValidStrictUuids
     */
    public function testValidStrictUuids($uuid, $versions = null)
    {
        $constraint = new Uuid();

        if (null !== $versions) {
            $constraint->versions = $versions;
        }

        $this->validator->validate($uuid, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidStrictUuids()
    {
        return [
            ['216fff40-98d9-11e3-a5e2-0800200c9a66'], // Version 1 UUID in lowercase
            ['216fff40-98d9-11e3-a5e2-0800200c9a66', [Uuid::V1_MAC]],
            ['216FFF40-98D9-11E3-A5E2-0800200C9A66'], // Version 1 UUID in UPPERCASE
            ['456daefb-5aa6-41b5-8dbc-068b05a8b201'], // Version 4 UUID in lowercase
            ['456daEFb-5AA6-41B5-8DBC-068B05A8B201'], // Version 4 UUID in mixed case
            ['456daEFb-5AA6-41B5-8DBC-068B05A8B201', [Uuid::V4_RANDOM]],
            ['1eb01932-4c0b-6570-aa34-d179cdf481ae', [Uuid::V6_SORTABLE]],
            ['216fff40-98d9-71e3-a5e2-0800200c9a66', [Uuid::V7_MONOTONIC]],
            ['216fff40-98d9-81e3-a5e2-0800200c9a66', [Uuid::V8_CUSTOM]],
        ];
    }

    /**
     * @dataProvider getValidStrictUuidsWithWhitespaces
     */
    public function testValidStrictUuidsWithWhitespaces($uuid, $versions = null)
    {
        $constraint = new Uuid(['normalizer' => 'trim']);

        if (null !== $versions) {
            $constraint->versions = $versions;
        }

        $this->validator->validate($uuid, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidStrictUuidsWithWhitespaces()
    {
        return [
            ["\x20216fff40-98d9-11e3-a5e2-0800200c9a66"], // Version 1 UUID in lowercase
            ["\x09\x09216fff40-98d9-11e3-a5e2-0800200c9a66", [Uuid::V1_MAC]],
            ["216FFF40-98D9-11E3-A5E2-0800200C9A66\x0A"], // Version 1 UUID in UPPERCASE
            ["456daefb-5aa6-41b5-8dbc-068b05a8b201\x0D\x0D"], // Version 4 UUID in lowercase
            ["\x00456daEFb-5AA6-41B5-8DBC-068B05A8B201\x00"], // Version 4 UUID in mixed case
            ["\x0B\x0B456daEFb-5AA6-41B5-8DBC-068B05A8B201\x0B\x0B", [Uuid::V4_RANDOM]],
        ];
    }

    public function testValidStrictUuidWithWhitespacesNamed()
    {
        $this->validator->validate(
            "\x09\x09216fff40-98d9-11e3-a5e2-0800200c9a66",
            new Uuid(normalizer: 'trim', versions: [Uuid::V1_MAC])
        );

        $this->assertNoViolation();
    }

    /**
     * @dataProvider getInvalidStrictUuids
     */
    public function testInvalidStrictUuids($uuid, $code, $versions = null)
    {
        $constraint = new Uuid([
            'message' => 'testMessage',
        ]);

        if (null !== $versions) {
            $constraint->versions = $versions;
        }

        $this->validator->validate($uuid, $constraint);

        $this->buildViolation('testMessage')
            ->setParameter('{{ value }}', '"'.$uuid.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public static function getInvalidStrictUuids()
    {
        return [
            ['216fff40-98d9-11e3-a5e2_0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR],
            ['216gff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR],
            ['216Gff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR],
            ['216fff40-98d9-11e3-a5e-20800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR],
            ['216f-ff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR],
            ['216fff40-98d9-11e3-a5e2-0800-200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR],
            ['216fff40-98d9-11e3-a5e2-0800200c-9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR],
            ['216fff40-98d9-11e3-a5e20800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR],
            ['216fff4098d911e3a5e20800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR],
            ['216fff40-98d9-11e3-a5e2-0800200c9a6', Uuid::TOO_SHORT_ERROR],
            ['216fff40-98d9-11e3-a5e2-0800200c9a666', Uuid::TOO_LONG_ERROR],
            ['216fff40-98d9-01e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR],
            ['216fff40-98d9-91e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR],
            ['216fff40-98d9-a1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR],
            ['216fff40-98d9-b1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR],
            ['216fff40-98d9-c1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR],
            ['216fff40-98d9-d1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR],
            ['216fff40-98d9-e1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR],
            ['216fff40-98d9-f1e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR],
            ['216fff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR, [Uuid::V2_DCE, Uuid::V3_MD5, Uuid::V4_RANDOM, Uuid::V5_SHA1]],
            ['216fff40-98d9-21e3-a5e2-0800200c9a66', Uuid::INVALID_VERSION_ERROR, [Uuid::V1_MAC, Uuid::V3_MD5, Uuid::V4_RANDOM, Uuid::V5_SHA1]],
            ['216fff40-98d9-11e3-05e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-15e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-25e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-35e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-45e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-55e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-65e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-75e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-c5e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-d5e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-e5e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],
            ['216fff40-98d9-11e3-f5e2-0800200c9a66', Uuid::INVALID_VARIANT_ERROR],

            // Non-standard UUID allowed by some other systems
            ['{216fff40-98d9-11e3-a5e2-0800200c9a66}', Uuid::INVALID_CHARACTERS_ERROR],
            ['[216fff40-98d9-11e3-a5e2-0800200c9a66]', Uuid::INVALID_CHARACTERS_ERROR],
        ];
    }

    /**
     * @dataProvider getValidNonStrictUuids
     */
    public function testValidNonStrictUuids($uuid)
    {
        $constraint = new Uuid([
            'strict' => false,
        ]);

        $this->validator->validate($uuid, $constraint);

        $this->assertNoViolation();
    }

    public static function getValidNonStrictUuids()
    {
        return [
            ['216fff40-98d9-11e3-a5e2-0800200c9a66'],    // Version 1 UUID in lowercase
            ['216FFF40-98D9-11E3-A5E2-0800200C9A66'],    // Version 1 UUID in UPPERCASE
            ['456daefb-5aa6-41b5-8dbc-068b05a8b201'],    // Version 4 UUID in lowercase
            ['456DAEFb-5AA6-41B5-8DBC-068b05a8B201'],    // Version 4 UUID in mixed case

            // Non-standard UUIDs allowed by some other systems
            ['216f-ff40-98d9-11e3-a5e2-0800-200c-9a66'], // Non-standard dash positions (every 4 chars)
            ['216fff40-98d911e3-a5e20800-200c9a66'],     // Non-standard dash positions (every 8 chars)
            ['216fff4098d911e3a5e20800200c9a66'],        // No dashes at all
            ['{216fff40-98d9-11e3-a5e2-0800200c9a66}'],  // Wrapped with curly braces
            ['[216fff40-98d9-11e3-a5e2-0800200c9a66]'],  // Wrapped with squared braces
        ];
    }

    /**
     * @dataProvider getInvalidNonStrictUuids
     */
    public function testInvalidNonStrictUuids($uuid, $code)
    {
        $constraint = new Uuid([
            'strict' => false,
            'message' => 'myMessage',
        ]);

        $this->validator->validate($uuid, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$uuid.'"')
            ->setCode($code)
            ->assertRaised();
    }

    public static function getInvalidNonStrictUuids()
    {
        return [
            ['216fff40-98d9-11e3-a5e2_0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR],
            ['216gff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR],
            ['216Gff40-98d9-11e3-a5e2-0800200c9a66', Uuid::INVALID_CHARACTERS_ERROR],
            ['216fff40-98d9-11e3-a5e2_0800200c9a6', Uuid::INVALID_CHARACTERS_ERROR],
            ['216fff40-98d9-11e3-a5e-20800200c9a66', Uuid::INVALID_HYPHEN_PLACEMENT_ERROR],
            ['216fff40-98d9-11e3-a5e2-0800200c9a6', Uuid::TOO_SHORT_ERROR],
            ['216fff40-98d9-11e3-a5e2-0800200c9a666', Uuid::TOO_LONG_ERROR],
        ];
    }

    public function testInvalidNonStrictUuidNamed()
    {
        $this->validator->validate(
            '216fff40-98d9-11e3-a5e2_0800200c9a66',
            new Uuid(strict: false, message: 'myMessage')
        );

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"216fff40-98d9-11e3-a5e2_0800200c9a66"')
            ->setCode(Uuid::INVALID_CHARACTERS_ERROR)
            ->assertRaised();
    }

    /**
     * @dataProvider getUuidForTimeBasedAssertions
     */
    public function testTimeBasedUuid(string $uid, bool $expectedTimeBased)
    {
        $constraint = new Uuid([
            'versions' => Uuid::TIME_BASED_VERSIONS,
        ]);

        $this->validator->validate($uid, $constraint);

        if ($expectedTimeBased) {
            $this->assertNoViolation();
        } else {
            $this->buildViolation('This is not a valid UUID.')
                ->setParameter('{{ value }}', '"'.$uid.'"')
                ->setCode(Uuid::INVALID_TIME_BASED_VERSION_ERROR)
                ->assertRaised();
        }
    }

    public static function getUuidForTimeBasedAssertions(): \Generator
    {
        yield Uuid::V1_MAC => ['95ab107e-6fc2-11ed-9d6b-01bfd6e71dbd', true];
        yield Uuid::V2_DCE => ['216fff40-98d9-21e3-a5e2-0800200c9a66', false];
        yield Uuid::V3_MD5 => ['5d5b5ae1-5857-3531-9431-e8ac73c3e61d', false];
        yield Uuid::V4_RANDOM => ['ba6479dd-a5ea-403c-96ae-5964d0582e81', false];
        yield Uuid::V5_SHA1 => ['fc1cc19d-cb3c-5f6a-a0f6-706424f68e3a', false];
        yield Uuid::V6_SORTABLE => ['1ed6fc29-5ab1-6b6e-8aad-cfa821180d14', true];
        yield Uuid::V7_MONOTONIC => ['0184c292-b133-7e10-a3b4-d49c1ab49b2a', true];
        yield Uuid::V8_CUSTOM => ['00112233-4455-8677-8899-aabbccddeeff', false];
    }
}
