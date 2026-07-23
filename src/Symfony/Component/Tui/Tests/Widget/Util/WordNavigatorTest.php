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
use Symfony\Component\Tui\Widget\Util\WordNavigator;

final class WordNavigatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function skipWordBackwardProvider(): iterable
    {
        yield 'at start of string' => ['hello world', 0, 0];
        yield 'skips word' => ['hello world', 11, 6];
        yield 'skips whitespace then word (from end)' => ['hello  world', 12, 7];
        yield 'skips whitespace then word (from mid)' => ['hello  world', 7, 0];
        yield 'skips punctuation (from end)' => ['hello...world', 13, 8];
        yield 'skips punctuation (from mid)' => ['hello...world', 8, 5];
        yield 'skips word after punctuation' => ['hello...world', 5, 0];
        yield 'trailing whitespace skips to start' => ['hello   ', 8, 0];
        yield 'mixed punctuation: bar' => ['foo->bar', 8, 5];
        yield 'mixed punctuation: ->' => ['foo->bar', 5, 3];
        yield 'mixed punctuation: foo' => ['foo->bar', 3, 0];
        yield 'multibyte: skips world' => ["caf\xC3\xA9 world", 11, 6];
        yield 'multibyte: skips café' => ["caf\xC3\xA9 world", 6, 0];
        yield 'empty string' => ['', 0, 0];
        yield 'whitespace only' => ['   ', 3, 0];
    }

    #[DataProvider('skipWordBackwardProvider')]
    public function testSkipWordBackward(string $text, int $cursor, int $expected)
    {
        $this->assertSame($expected, WordNavigator::skipWordBackward($text, $cursor));
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function skipWordForwardProvider(): iterable
    {
        yield 'at end of string' => ['hello', 5, 5];
        yield 'skips word' => ['hello world', 0, 5];
        yield 'skips whitespace then word' => ['hello  world', 5, 12];
        yield 'skips punctuation from start' => ['hello...world', 0, 5];
        yield 'skips punctuation dots' => ['hello...world', 5, 8];
        yield 'skips word after punctuation' => ['hello...world', 8, 13];
        yield 'leading whitespace' => ['   hello', 0, 8];
        yield 'mixed punctuation: foo' => ['foo->bar', 0, 3];
        yield 'mixed punctuation: ->' => ['foo->bar', 3, 5];
        yield 'mixed punctuation: bar' => ['foo->bar', 5, 8];
        yield 'multibyte: skips café' => ["caf\xC3\xA9 world", 0, 5];
        yield 'multibyte: skips world' => ["caf\xC3\xA9 world", 5, 11];
        yield 'empty string' => ['', 0, 0];
        yield 'whitespace only' => ['   ', 0, 3];
    }

    #[DataProvider('skipWordForwardProvider')]
    public function testSkipWordForward(string $text, int $cursor, int $expected)
    {
        $this->assertSame($expected, WordNavigator::skipWordForward($text, $cursor));
    }

    public function testRoundTripWordNavigation()
    {
        $text = 'hello  world...foo';
        // Forward: 0 → 5 → 12 → 15 → 18
        $pos = 0;
        $pos = WordNavigator::skipWordForward($text, $pos);
        $this->assertSame(5, $pos);
        $pos = WordNavigator::skipWordForward($text, $pos);
        $this->assertSame(12, $pos);
        $pos = WordNavigator::skipWordForward($text, $pos);
        $this->assertSame(15, $pos);
        $pos = WordNavigator::skipWordForward($text, $pos);
        $this->assertSame(18, $pos);

        // Backward: 18 → 15 → 12 → 7 → 0
        $pos = WordNavigator::skipWordBackward($text, $pos);
        $this->assertSame(15, $pos);
        $pos = WordNavigator::skipWordBackward($text, $pos);
        $this->assertSame(12, $pos);
        $pos = WordNavigator::skipWordBackward($text, $pos);
        $this->assertSame(7, $pos);
        $pos = WordNavigator::skipWordBackward($text, $pos);
        $this->assertSame(0, $pos);
    }
}
