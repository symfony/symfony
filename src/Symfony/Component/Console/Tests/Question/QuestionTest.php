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
use Symfony\Component\Console\Question\Question;

class QuestionTest extends TestCase
{
    private Question $question;

    protected function setUp(): void
    {
        $this->question = new Question('Test question');
    }

    public static function providerTrueFalse()
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

    public function testSetHiddenWithAutocompleterCallback()
    {
        $this->question->setAutocompleterCallback(
            fn (string $input): array => []
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'A hidden question cannot use the autocompleter.'
        );

        $this->question->setHidden(true);
    }

    public function testSetHiddenWithNoAutocompleterCallback()
    {
        $this->question->setAutocompleterCallback(
            fn (string $input): array => []
        );
        $this->question->setAutocompleterCallback(null);

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

    public static function providerGetSetAutocompleterValues()
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

    public static function providerSetAutocompleterValuesInvalid()
    {
        return [
            ['Potato'],
            [new \stdClass()],
            [false],
        ];
    }

    /**
     * @dataProvider providerSetAutocompleterValuesInvalid
     */
    public function testSetAutocompleterValuesInvalid($values)
    {
        self::expectException(\TypeError::class);

        $this->question->setAutocompleterValues($values);
    }

    public function testSetAutocompleterValuesWithTraversable()
    {
        $question1 = new Question('Test question 1');
        $iterator1 = $this->createMock(\IteratorAggregate::class);
        $iterator1
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator(['Potato']));
        $question1->setAutocompleterValues($iterator1);

        $question2 = new Question('Test question 2');
        $iterator2 = $this->createMock(\IteratorAggregate::class);
        $iterator2
            ->expects($this->once())
            ->method('getIterator')
            ->willReturn(new \ArrayIterator(['Carrot']));
        $question2->setAutocompleterValues($iterator2);

        // Call multiple times to verify that Traversable result is cached, and
        // that there is no crosstalk between cached copies.
        self::assertSame(['Potato'], $question1->getAutocompleterValues());
        self::assertSame(['Carrot'], $question2->getAutocompleterValues());
        self::assertSame(['Potato'], $question1->getAutocompleterValues());
        self::assertSame(['Carrot'], $question2->getAutocompleterValues());
    }

    public function testGetAutocompleterValuesDefault()
    {
        self::assertNull($this->question->getAutocompleterValues());
    }

    public function testGetSetAutocompleterCallback()
    {
        $callback = fn (string $input): array => [];

        $this->question->setAutocompleterCallback($callback);
        self::assertSame($callback, $this->question->getAutocompleterCallback());
    }

    public function testGetAutocompleterCallbackDefault()
    {
        self::assertNull($this->question->getAutocompleterCallback());
    }

    public function testSetAutocompleterCallbackWhenHidden()
    {
        $this->question->setHidden(true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            'A hidden question cannot use the autocompleter.'
        );

        $this->question->setAutocompleterCallback(
            fn (string $input): array => []
        );
    }

    public function testSetAutocompleterCallbackWhenNotHidden()
    {
        $this->question->setHidden(true);
        $this->question->setHidden(false);

        $exception = null;
        try {
            $this->question->setAutocompleterCallback(
                fn (string $input): array => []
            );
        } catch (\Exception $exception) {
            // Do nothing
        }

        $this->assertNull($exception);
    }

    public static function providerGetSetValidator()
    {
        return [
            [fn ($input) => $input],
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

    public static function providerGetSetMaxAttempts()
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

    public static function providerSetMaxAttemptsInvalid()
    {
        return [[0], [-1]];
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
        $normalizer = fn ($input) => $input;
        $this->question->setNormalizer($normalizer);
        self::assertSame($normalizer, $this->question->getNormalizer());
    }

    public function testGetNormalizerDefault()
    {
        self::assertNull($this->question->getNormalizer());
    }

    /**
     * @dataProvider providerTrueFalse
     */
    public function testSetMultiline(bool $multiline)
    {
        self::assertSame($this->question, $this->question->setMultiline($multiline));
        self::assertSame($multiline, $this->question->isMultiline());
    }

    public function testIsMultilineDefault()
    {
        self::assertFalse($this->question->isMultiline());
    }
}
