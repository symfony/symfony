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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Question\Question;

class QuestionTest extends TestCase
{
    private $question;

    protected function setUp()
    {
        parent::setUp();
        $this->question = new Question('Test question');
    }

    public function providerTrueFalse()
    {
        return [[true], [false]];
    }

    public function testGetQuestion()
    {
        self::assertSame('Test question', $this->question->getQuestion());
    }

    public function testGetDefault()
    {
        $question = new Question('Test question', 'Default value');
        self::assertSame('Default value', $question->getDefault());
    }

    public function testGetDefaultDefault()
    {
        self::assertNull($this->question->getDefault());
    }

    /**
     * @dataProvider providerTrueFalse
     */
    public function testIsSetHidden(bool $hidden)
    {
        $this->question->setHidden($hidden);
        self::assertSame($hidden, $this->question->isHidden());
    }

    public function testIsHiddenDefault()
    {
        self::assertFalse($this->question->isHidden());
    }

    public function testSetHiddenWithAutocompleterValues()
    {
        $this->question->setAutocompleterValues(['a', 'b']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'A hidden question cannot use the autocompleter.'
        );

        $this->question->setHidden(true);
    }

    public function testSetHiddenWithNoAutocompleterValues()
    {
        $this->question->setAutocompleterValues(['a', 'b']);
        $this->question->setAutocompleterValues(null);

        $exception = null;
        try {
            $this->question->setHidden(true);
        } catch (\Exception $exception) {
            // Do nothing
        }

        $this->assertNull($exception);
    }

    /**
     * @dataProvider providerTrueFalse
     */
    public function testIsSetHiddenFallback(bool $hidden)
    {
        $this->question->setHiddenFallback($hidden);
        self::assertSame($hidden, $this->question->isHiddenFallback());
    }

    public function testIsHiddenFallbackDefault()
    {
        self::assertTrue($this->question->isHiddenFallback());
    }

    public function providerGetSetAutocompleterValues()
    {
        return [
            'array' => [
                ['a', 'b', 'c', 'd'],
                ['a', 'b', 'c', 'd'],
            ],
            'associative array' => [
                ['a' => 'c', 'b' => 'd'],
                ['a', 'b', 'c', 'd'],
            ],
            'iterator' => [
                new \ArrayIterator(['a', 'b', 'c', 'd']),
                ['a', 'b', 'c', 'd'],
            ],
            'null' => [null, null],
        ];
    }

    /**
     * @dataProvider providerGetSetAutocompleterValues
     */
    public function testGetSetAutocompleterValues($values, $expectValues)
    {
        $this->question->setAutocompleterValues($values);
        self::assertSame(
            $expectValues,
            $this->question->getAutocompleterValues()
        );
    }

    public function providerSetAutocompleterValuesInvalid()
    {
        return [
            ['Potato'],
            [new \stdclass()],
            [false],
        ];
    }

    /**
     * @dataProvider providerSetAutocompleterValuesInvalid
     */
    public function testSetAutocompleterValuesInvalid($values)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(
            'Autocompleter values can be either an array, "null" or a "Traversable" object.'
        );

        $this->question->setAutocompleterValues($values);
    }

    public function testSetAutocompleterValuesWhenHidden()
    {
        $this->question->setHidden(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'A hidden question cannot use the autocompleter.'
        );

        $this->question->setAutocompleterValues(['a', 'b']);
    }

    public function testSetAutocompleterValuesWhenNotHidden()
    {
        $this->question->setHidden(true);
        $this->question->setHidden(false);

        $exception = null;
        try {
            $this->question->setAutocompleterValues(['a', 'b']);
        } catch (\Exception $exception) {
            // Do nothing
        }

        $this->assertNull($exception);
    }

    public function testGetAutocompleterValuesDefault()
    {
        self::assertNull($this->question->getAutocompleterValues());
    }

    public function providerGetSetValidator()
    {
        return [
            [function ($input) { return $input; }],
            [null],
        ];
    }

    /**
     * @dataProvider providerGetSetValidator
     */
    public function testGetSetValidator($callback)
    {
        $this->question->setValidator($callback);
        self::assertSame($callback, $this->question->getValidator());
    }

    public function testGetValidatorDefault()
    {
        self::assertNull($this->question->getValidator());
    }

    public function providerGetSetMaxAttempts()
    {
        return [[1], [5], [null]];
    }

    /**
     * @dataProvider providerGetSetMaxAttempts
     */
    public function testGetSetMaxAttempts($attempts)
    {
        $this->question->setMaxAttempts($attempts);
        self::assertSame($attempts, $this->question->getMaxAttempts());
    }

    public function providerSetMaxAttemptsInvalid()
    {
        return [['Potato'], [0], [-1]];
    }

    /**
     * @dataProvider providerSetMaxAttemptsInvalid
     */
    public function testSetMaxAttemptsInvalid($attempts)
    {
        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('Maximum number of attempts must be a positive value.');

        $this->question->setMaxAttempts($attempts);
    }

    public function testGetMaxAttemptsDefault()
    {
        self::assertNull($this->question->getMaxAttempts());
    }

    public function testGetSetNormalizer()
    {
        $normalizer = function ($input) { return $input; };
        $this->question->setNormalizer($normalizer);
        self::assertSame($normalizer, $this->question->getNormalizer());
    }

    public function testGetNormalizerDefault()
    {
        self::assertNull($this->question->getNormalizer());
    }
}
