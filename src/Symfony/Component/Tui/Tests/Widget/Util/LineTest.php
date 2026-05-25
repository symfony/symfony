<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Widget\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Widget\Util\Line;

class LineTest extends TestCase
{
    // --- bounds clamping ---

    public function testConstructorClampsNegativeCursor()
    {
        $line = new Line('hello', -5);
        $this->assertSame(0, $line->getCursor());
    }

    public function testConstructorClampsCursorBeyondEnd()
    {
        $line = new Line('hello', 100);
        $this->assertSame(5, $line->getCursor());
    }

    public function testSetCursorClampsNegativeValue()
    {
        $line = new Line('hello', 3);
        $line->setCursor(-1);
        $this->assertSame(0, $line->getCursor());
    }

    public function testSetCursorClampsBeyondEnd()
    {
        $line = new Line('hello', 0);
        $line->setCursor(100);
        $this->assertSame(5, $line->getCursor());
    }

    public function testSetTextAdjustsCursorWhenBeyondNewLength()
    {
        $line = new Line('hello world', 10);
        $line->setText('hi');
        $this->assertSame('hi', $line->getText());
        $this->assertSame(2, $line->getCursor());
    }

    public function testSetTextKeepsCursorWhenWithinNewLength()
    {
        $line = new Line('hello', 3);
        $line->setText('world!');
        $this->assertSame('world!', $line->getText());
        $this->assertSame(3, $line->getCursor());
    }

    // --- insert ---

    #[DataProvider('insertProvider')]
    public function testInsert(string $text, int $cursor, string $insert, string $expectedText, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $line->insert($insert);
        $this->assertSame($expectedText, $line->getText());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, string, string, int}>
     */
    public static function insertProvider(): iterable
    {
        yield 'at start' => ['world', 0, 'hello ', 'hello world', 6];
        yield 'at end' => ['hello', 5, ' world', 'hello world', 11];
        yield 'in middle' => ['helo', 2, 'l', 'hello', 3];
        yield 'multibyte (é)' => ['caf', 3, 'é', 'café', 5];
        yield 'emoji (👋)' => ['hello', 5, '👋', 'hello👋', 9];
    }

    // --- deleteCharBackward ---

    #[DataProvider('deleteCharBackwardProvider')]
    public function testDeleteCharBackward(string $text, int $cursor, bool $expectedReturn, string $expectedText, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedReturn, $line->deleteCharBackward());
        $this->assertSame($expectedText, $line->getText());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, bool, string, int}>
     */
    public static function deleteCharBackwardProvider(): iterable
    {
        yield 'ascii at end' => ['Hello', 5, true, 'Hell', 4];
        yield 'at start (noop)' => ['Hello', 0, false, 'Hello', 0];
        yield 'multibyte (é)' => ['café', 5, true, 'caf', 3];
        yield 'emoji' => ['hello👋', 9, true, 'hello', 5];
        yield 'CJK' => ['日本語', 9, true, '日本', 6];
        yield 'in middle' => ['Hello', 3, true, 'Helo', 2];
    }

    // --- deleteCharForward ---

    #[DataProvider('deleteCharForwardProvider')]
    public function testDeleteCharForward(string $text, int $cursor, bool $expectedReturn, string $expectedText, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedReturn, $line->deleteCharForward());
        $this->assertSame($expectedText, $line->getText());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, bool, string, int}>
     */
    public static function deleteCharForwardProvider(): iterable
    {
        yield 'ascii at start' => ['Hello', 0, true, 'ello', 0];
        yield 'at end (noop)' => ['Hello', 5, false, 'Hello', 5];
        yield 'multibyte (é)' => ['café', 3, true, 'caf', 3];
        yield 'emoji' => ['👋hello', 0, true, 'hello', 0];
        yield 'CJK' => ['日本語', 0, true, '本語', 0];
    }

    // --- moveCursorLeft ---

    #[DataProvider('moveCursorLeftProvider')]
    public function testMoveCursorLeft(string $text, int $cursor, bool $expectedReturn, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedReturn, $line->moveCursorLeft());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, bool, int}>
     */
    public static function moveCursorLeftProvider(): iterable
    {
        yield 'ascii' => ['Hello', 5, true, 4];
        yield 'at start (noop)' => ['Hello', 0, false, 0];
        yield 'multibyte (é)' => ['café', 5, true, 3];
        yield 'emoji' => ['a👋', 5, true, 1];
    }

    // --- moveCursorRight ---

    #[DataProvider('moveCursorRightProvider')]
    public function testMoveCursorRight(string $text, int $cursor, bool $expectedReturn, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedReturn, $line->moveCursorRight());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, bool, int}>
     */
    public static function moveCursorRightProvider(): iterable
    {
        yield 'ascii' => ['Hello', 0, true, 1];
        yield 'at end (noop)' => ['Hello', 5, false, 5];
        yield 'multibyte (é)' => ['café', 3, true, 5];
        yield 'emoji' => ['👋a', 0, true, 4];
    }

    // --- moveCursorToStart / moveCursorToEnd ---

    public function testMoveCursorToStart()
    {
        $line = new Line('Hello', 3);
        $this->assertTrue($line->moveCursorToStart());
        $this->assertSame(0, $line->getCursor());
    }

    public function testMoveCursorToStartAlreadyAtStart()
    {
        $line = new Line('Hello', 0);
        $this->assertFalse($line->moveCursorToStart());
    }

    public function testMoveCursorToEnd()
    {
        $line = new Line('Hello', 0);
        $this->assertTrue($line->moveCursorToEnd());
        $this->assertSame(5, $line->getCursor());
    }

    public function testMoveCursorToEndAlreadyAtEnd()
    {
        $line = new Line('Hello', 5);
        $this->assertFalse($line->moveCursorToEnd());
    }

    // --- moveWordBackward / moveWordForward ---

    #[DataProvider('moveWordBackwardProvider')]
    public function testMoveWordBackward(string $text, int $cursor, bool $expectedReturn, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedReturn, $line->moveWordBackward());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, bool, int}>
     */
    public static function moveWordBackwardProvider(): iterable
    {
        yield 'moves to word start' => ['hello world', 11, true, 6];
        yield 'at start (noop)' => ['hello', 0, false, 0];
    }

    #[DataProvider('moveWordForwardProvider')]
    public function testMoveWordForward(string $text, int $cursor, bool $expectedReturn, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedReturn, $line->moveWordForward());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, bool, int}>
     */
    public static function moveWordForwardProvider(): iterable
    {
        yield 'moves to word end' => ['hello world', 0, true, 5];
        yield 'at end (noop)' => ['hello', 5, false, 5];
    }

    // --- deleteWordBackward ---

    #[DataProvider('deleteWordBackwardProvider')]
    public function testDeleteWordBackward(string $text, int $cursor, string $expectedDeleted, string $expectedText, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedDeleted, $line->deleteWordBackward());
        $this->assertSame($expectedText, $line->getText());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, string, string, int}>
     */
    public static function deleteWordBackwardProvider(): iterable
    {
        yield 'simple' => ['hello world', 11, 'world', 'hello ', 6];
        yield 'at start (noop)' => ['hello', 0, '', 'hello', 0];
        yield 'multibyte' => ['hello café', 11, 'café', 'hello ', 6];
    }

    // --- deleteWordForward ---

    #[DataProvider('deleteWordForwardProvider')]
    public function testDeleteWordForward(string $text, int $cursor, string $expectedDeleted, string $expectedText, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedDeleted, $line->deleteWordForward());
        $this->assertSame($expectedText, $line->getText());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, string, string, int}>
     */
    public static function deleteWordForwardProvider(): iterable
    {
        yield 'simple' => ['hello world', 0, 'hello', ' world', 0];
        yield 'at end (noop)' => ['hello', 5, '', 'hello', 5];
    }

    // --- deleteToEnd / deleteToStart ---

    #[DataProvider('deleteToEndProvider')]
    public function testDeleteToEnd(string $text, int $cursor, string $expectedDeleted, string $expectedText, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedDeleted, $line->deleteToEnd());
        $this->assertSame($expectedText, $line->getText());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, string, string, int}>
     */
    public static function deleteToEndProvider(): iterable
    {
        yield 'in middle' => ['Hello World', 5, ' World', 'Hello', 5];
        yield 'at end (noop)' => ['Hello', 5, '', 'Hello', 5];
    }

    #[DataProvider('deleteToStartProvider')]
    public function testDeleteToStart(string $text, int $cursor, string $expectedDeleted, string $expectedText, int $expectedCursor)
    {
        $line = new Line($text, $cursor);
        $this->assertSame($expectedDeleted, $line->deleteToStart());
        $this->assertSame($expectedText, $line->getText());
        $this->assertSame($expectedCursor, $line->getCursor());
    }

    /**
     * @return iterable<string, array{string, int, string, string, int}>
     */
    public static function deleteToStartProvider(): iterable
    {
        yield 'in middle' => ['Hello World', 5, 'Hello', ' World', 0];
        yield 'at start (noop)' => ['Hello', 0, '', 'Hello', 0];
    }
}
