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

use Symfony\Component\Validator\Constraints\NoSuspiciousCharacters;
use Symfony\Component\Validator\Constraints\NoSuspiciousCharactersValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @requires extension intl
 *
 * @extends ConstraintValidatorTestCase<NoSuspiciousCharactersValidator>
 */
class NoSuspiciousCharactersValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NoSuspiciousCharactersValidator
    {
        return new NoSuspiciousCharactersValidator();
    }

    /**
     * @dataProvider provideNonSuspiciousStrings
     */
    public function testNonSuspiciousStrings(string $string, array $options = [])
    {
        $this->validator->validate($string, new NoSuspiciousCharacters($options));

        $this->assertNoViolation();
    }

    public static function provideNonSuspiciousStrings(): iterable
    {
        yield 'Characters from Common script can only fail RESTRICTION_LEVEL_ASCII' => [
            'I ❤️ Unicode',
            ['restrictionLevel' => NoSuspiciousCharacters::RESTRICTION_LEVEL_SINGLE_SCRIPT],
        ];

        yield 'RESTRICTION_LEVEL_MINIMAL cannot fail without configured locales' => [
            'àㄚԱπ৪',
            [
                'restrictionLevel' => NoSuspiciousCharacters::RESTRICTION_LEVEL_MINIMAL,
                'locales' => [],
            ],
        ];
    }

    /**
     * @dataProvider provideSuspiciousStrings
     */
    public function testSuspiciousStrings(string $string, array $options, string $errorCode, string $errorMessage)
    {
        $this->validator->validate($string, new NoSuspiciousCharacters($options));

        $this->buildViolation($errorMessage)
            ->setCode($errorCode)
            ->setParameter('{{ value }}', '"'.$string.'"')
            ->assertRaised();
    }

    public static function provideSuspiciousStrings(): iterable
    {
        yield 'Fails RESTRICTION_LEVEL check because of character outside ASCII range' => [
            'à',
            ['restrictionLevel' => NoSuspiciousCharacters::RESTRICTION_LEVEL_ASCII],
            NoSuspiciousCharacters::RESTRICTION_LEVEL_ERROR,
            'This value contains characters that are not allowed by the current restriction-level.',
        ];

        yield 'Fails RESTRICTION_LEVEL check because of mixed-script string' => [
            'àㄚ',
            [
                'restrictionLevel' => NoSuspiciousCharacters::RESTRICTION_LEVEL_SINGLE_SCRIPT,
                'locales' => ['en', 'zh_Hant_TW'],
            ],
            NoSuspiciousCharacters::RESTRICTION_LEVEL_ERROR,
            'This value contains characters that are not allowed by the current restriction-level.',
        ];

        yield 'Fails RESTRICTION_LEVEL check because RESTRICTION_LEVEL_HIGH disallows Armenian script' => [
            'àԱ',
            [
                'restrictionLevel' => NoSuspiciousCharacters::RESTRICTION_LEVEL_HIGH,
                'locales' => ['en', 'hy_AM'],
            ],
            NoSuspiciousCharacters::RESTRICTION_LEVEL_ERROR,
            'This value contains characters that are not allowed by the current restriction-level.',
        ];

        yield 'Fails RESTRICTION_LEVEL check because RESTRICTION_LEVEL_MODERATE disallows Greek script' => [
            'àπ',
            [
                'restrictionLevel' => NoSuspiciousCharacters::RESTRICTION_LEVEL_MODERATE,
                'locales' => ['en', 'el_GR'],
            ],
            NoSuspiciousCharacters::RESTRICTION_LEVEL_ERROR,
            'This value contains characters that are not allowed by the current restriction-level.',
        ];

        yield 'Fails RESTRICTION_LEVEL check because of characters missing from the configured locales’ scripts' => [
            'àπ',
            [
                'restrictionLevel' => NoSuspiciousCharacters::RESTRICTION_LEVEL_MINIMAL,
                'locales' => ['en'],
            ],
            NoSuspiciousCharacters::RESTRICTION_LEVEL_ERROR,
            'This value contains characters that are not allowed by the current restriction-level.',
        ];

        yield 'Fails INVISIBLE check because of duplicated non-spacing mark' => [
            'à̀',
            [
                'checks' => NoSuspiciousCharacters::CHECK_INVISIBLE,
            ],
            NoSuspiciousCharacters::INVISIBLE_ERROR,
            'Using invisible characters is not allowed.',
        ];

        yield 'Fails MIXED_NUMBERS check because of different numbering systems' => [
            '8৪',
            [
                'checks' => NoSuspiciousCharacters::CHECK_MIXED_NUMBERS,
            ],
            NoSuspiciousCharacters::MIXED_NUMBERS_ERROR,
            'Mixing numbers from different scripts is not allowed.',
        ];

        yield 'Fails HIDDEN_OVERLAY check because of hidden combining character' => [
            'i̇',
            [
                'checks' => NoSuspiciousCharacters::CHECK_HIDDEN_OVERLAY,
            ],
            NoSuspiciousCharacters::HIDDEN_OVERLAY_ERROR,
            'Using hidden overlay characters is not allowed.',
        ];
    }

    public function testConstants()
    {
        $this->assertSame(\Spoofchecker::INVISIBLE, NoSuspiciousCharacters::CHECK_INVISIBLE);
        $this->assertSame(\Spoofchecker::ASCII, NoSuspiciousCharacters::RESTRICTION_LEVEL_ASCII);
        $this->assertSame(\Spoofchecker::SINGLE_SCRIPT_RESTRICTIVE, NoSuspiciousCharacters::RESTRICTION_LEVEL_SINGLE_SCRIPT);
        $this->assertSame(\Spoofchecker::HIGHLY_RESTRICTIVE, NoSuspiciousCharacters::RESTRICTION_LEVEL_HIGH);
        $this->assertSame(\Spoofchecker::MODERATELY_RESTRICTIVE, NoSuspiciousCharacters::RESTRICTION_LEVEL_MODERATE);
        $this->assertSame(\Spoofchecker::MINIMALLY_RESTRICTIVE, NoSuspiciousCharacters::RESTRICTION_LEVEL_MINIMAL);
        $this->assertSame(\Spoofchecker::UNRESTRICTIVE, NoSuspiciousCharacters::RESTRICTION_LEVEL_NONE);
    }
}
