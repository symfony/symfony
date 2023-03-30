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

use Symfony\Component\Validator\Constraints\NoBannedWords;
use Symfony\Component\Validator\Constraints\NoBannedWordsValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class NoBannedWordsValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): NoBannedWordsValidator
    {
        return new NoBannedWordsValidator();
    }

    /**
     * @dataProvider getValidValues
     */
    public function testValidValues(string $value)
    {
        $this->validator->validate($value, new NoBannedWords([
            'dictionary' => ['foo', 'bar'],
        ]));

        $this->assertNoViolation();
    }

    public static function getValidValues(): iterable
    {
        yield ['This text does not contain any banned words.'];
        yield ['Another text that does not contain any banned words'];
    }

    /**
     * @dataProvider provideInvalidConstraints
     */
    public function testBannedWordsAreCatched(NoBannedWords $constraint, string $password, string $expectedMessage, string $expectedCode, array $parameters = [])
    {
        $this->validator->validate($password, $constraint);

        $this->buildViolation($expectedMessage)
            ->setCode($expectedCode)
            ->setParameters($parameters)
            ->assertRaised();
    }

    public static function provideInvalidConstraints(): iterable
    {
        yield [
            new NoBannedWords([
                'dictionary' => ['symfony'],
            ]),
            'This text contains symfony, which is not allowed.',
            'The value contains the following banned words: {{ wordList }}.',
            NoBannedWords::BANNED_WORDS_ERROR,
            [
                '{{ matches }}' => 'symfony',
                '{{ dictionary }}' => 'symfony',
            ],
        ];
        yield [
            new NoBannedWords([
                'dictionary' => ['symfony'],
            ]),
            'This text contains $yMph0NY, which is a banned words written in l337.',
            'The value contains the following banned words: {{ wordList }}.',
            NoBannedWords::BANNED_WORDS_ERROR,
            [
                '{{ matches }}' => '$yMph0NY',
                '{{ dictionary }}' => 'symfony',
            ],
        ];
        yield [
            new NoBannedWords([
                'dictionary' => ['symfony', 'foo', 'bar'],
            ]),
            'This text contains $yMph0NY, f00 and b4r, which are all banned words written in l337.',
            'The value contains the following banned words: {{ wordList }}.',
            NoBannedWords::BANNED_WORDS_ERROR,
            [
                '{{ matches }}' => '$yMph0NY, f00, b4r',
                '{{ dictionary }}' => 'symfony, foo, bar',
            ],
        ];
    }
}
