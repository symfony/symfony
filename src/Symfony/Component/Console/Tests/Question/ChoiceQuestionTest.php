<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Question;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ChoiceQuestionTest extends TestCase
{
    /**
     * @dataProvider selectUseCases
     */
    public function testSelectUseCases($multiSelect, $answers, $expected, $message, $default = null)
    {
        $question = new ChoiceQuestion('A question', [
            'First response',
            'Second response',
            'Third response',
            'Fourth response',
            null,
        ], $default);

        $question->setMultiselect($multiSelect);

        foreach ($answers as $answer) {
            $validator = $question->getValidator();
            $actual = $validator($answer);

            $this->assertEquals($actual, $expected, $message);
        }
    }

    public static function selectUseCases()
    {
        return [
            [
                false,
                ['First response', 'First response ', ' First response', ' First response '],
                'First response',
                'When passed single answer on singleSelect, the defaultValidator must return this answer as a string',
            ],
            [
                true,
                ['First response', 'First response ', ' First response', ' First response '],
                ['First response'],
                'When passed single answer on MultiSelect, the defaultValidator must return this answer as an array',
            ],
            [
                true,
                ['First response,Second response', ' First response , Second response '],
                ['First response', 'Second response'],
                'When passed multiple answers on MultiSelect, the defaultValidator must return these answers as an array',
            ],
            [
                false,
                [null],
                null,
                'When used null as default single answer on singleSelect, the defaultValidator must return this answer as null',
            ],
            [
                false,
                ['First response'],
                'First response',
                'When used a string as default single answer on singleSelect, the defaultValidator must return this answer as a string',
                'First response',
            ],
            [
                false,
                [0],
                'First response',
                'When passed single answer using choice\'s key, the defaultValidator must return the choice value',
            ],
            [
                true,
                ['0, 2'],
                ['First response', 'Third response'],
                'When passed multiple answers using choices\' key, the defaultValidator must return the choice values in an array',
            ],
        ];
    }

    public function testNonTrimmable()
    {
        $question = new ChoiceQuestion('A question', [
            'First response ',
            ' Second response',
            '  Third response  ',
        ]);
        $question->setTrimmable(false);

        $this->assertSame('  Third response  ', $question->getValidator()('  Third response  '));

        $question->setMultiselect(true);

        $this->assertSame(['First response ', ' Second response'], $question->getValidator()('First response , Second response'));
    }

    /**
     * @dataProvider selectAssociativeChoicesProvider
     */
    public function testSelectAssociativeChoices($providedAnswer, $expectedValue)
    {
        $question = new ChoiceQuestion('A question', [
            '0' => 'First choice',
            'foo' => 'Foo',
            '99' => 'N°99',
            'string object' => new StringChoice('String Object'),
        ]);

        $this->assertSame($expectedValue, $question->getValidator()($providedAnswer));
    }

    public static function selectAssociativeChoicesProvider()
    {
        return [
            'select "0" choice by key' => ['0', '0'],
            'select "0" choice by value' => ['First choice', '0'],
            'select by key' => ['foo', 'foo'],
            'select by value' => ['Foo', 'foo'],
            'select by key, with numeric key' => ['99', '99'],
            'select by value, with numeric key' => ['N°99', '99'],
            'select by key, with string object value' => ['string object', 'string object'],
            'select by value, with string object value' => ['String Object', 'string object'],
        ];
    }

    public function testSelectWithNonStringChoices()
    {
        $question = new ChoiceQuestion('A question', [
            $result1 = new StringChoice('foo'),
            $result2 = new StringChoice('bar'),
            $result3 = new StringChoice('baz'),
        ]);

        $this->assertSame($result1, $question->getValidator()('foo'), 'answer can be selected by its string value');
        $this->assertSame($result1, $question->getValidator()(0), 'answer can be selected by index');

        $question->setMultiselect(true);

        $this->assertSame([$result3, $result2], $question->getValidator()('baz, bar'));
    }
}

class StringChoice
{
    private $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    public function __toString(): string
    {
        return $this->string;
    }
}
