<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Ansi;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\Bidi;

#[RequiresPhpExtension('intl')]
final class BidiTest extends TestCase
{
    private const CLASSES = ['L', 'R', 'EN', 'ES', 'ET', 'AN', 'CS', 'B', 'S', 'WS', 'ON', 'LRE', 'LRO', 'AL', 'RLE', 'RLO', 'PDF', 'NSM', 'BN', 'FSI', 'LRI', 'RLI', 'PDI'];

    /**
     * @return iterable<string, array{string, int, string, string}>
     */
    public static function provideConformance(): iterable
    {
        foreach (file(__DIR__.'/../Fixtures/Bidi/BidiCharacterTest.txt', \FILE_IGNORE_NEW_LINES) ?: [] as $number => $line) {
            if ('' === $line || '#' === $line[0]) {
                continue;
            }
            [$codePoints, $direction, $level, $levels, $order] = explode(';', $line);
            yield 'line '.($number + 1) => [$codePoints, (int) $direction, $levels, $order];
        }
    }

    /**
     * The cases of BidiCharacterTest.txt: the levels resolved and the visual order.
     */
    #[DataProvider('provideConformance')]
    public function testConformance(string $codePoints, int $direction, string $levels, string $order)
    {
        if (version_compare(implode('.', \array_slice(\IntlChar::getUnicodeVersion(), 0, 2)), '17.0', '<')) {
            $this->markTestSkipped('The cases are of Unicode 17.');
        }
        $characters = array_map(static fn (string $hex) => mb_chr((int) hexdec($hex)), explode(' ', $codePoints));
        $types = array_map(static fn (string $character) => self::CLASSES[\IntlChar::charDirection($character)], $characters);
        $paragraph = 2 === $direction ? Bidi::direction(implode('', $characters)) ?? 0 : $direction;

        $resolved = Bidi::resolveLevels($types, $paragraph, $characters);

        $this->assertSame($levels, implode(' ', array_map(static fn (?int $level) => $level ?? 'x', $resolved)));
        $this->assertSame($order, implode(' ', Bidi::visualOrder($resolved)));
    }

    public function testReorderLineKeepsStylesAndLinks()
    {
        $this->assertSame('abc', Bidi::reorderLine('abc', 0));
        $this->assertSame('גבא abc', Bidi::reorderLine('abc אבג', 1), 'In a right-to-left paragraph, the runs from the right.');
        $this->assertSame("abc ג\e[1mבא\e[0m", Bidi::reorderLine("abc \e[1mאב\e[22mג", 0), 'Each character in its style.');
        $this->assertSame("\e]8;id=1;https://example.com\x07בא\e]8;;\x07 x", Bidi::reorderLine("\e]8;id=1;https://example.com\x07אב\e]8;;\x07 x", 0), 'And its link.');
        $this->assertSame('(גב)א', Bidi::reorderLine('א(בג)', 0), 'Brackets mirrored in a right-to-left run.');
        $this->assertSame('a cba d', Bidi::reorderLine("a \u{202E}abc\u{202C} d", 0), 'Overridden, the controls not drawn.');
        $this->assertSame("x \e]5113;r0\x07בא\e]5113;R0\x07 y", Bidi::reorderLine("x \e]5113;r0\x07אב\e]5113;R0\x07 y", 0), 'A marked span around where its characters went.');
    }

    public function testTerminalsThatReorderTextThemselvesGetItAsStored()
    {
        try {
            putenv('TERM_PROGRAM=Apple_Terminal');
            $this->assertFalse(Bidi::isAvailable(), 'Terminal.app reorders the lines itself.');
            Bidi::setEnabled(true);
            $this->assertTrue(Bidi::isAvailable(), 'Unless the application says otherwise.');
            Bidi::setEnabled(null);
            putenv('TERM_PROGRAM=');
            $this->assertTrue(Bidi::isAvailable());
            Bidi::setEnabled(false);
            $this->assertFalse(Bidi::isAvailable());
        } finally {
            Bidi::setEnabled(null);
            putenv('TERM_PROGRAM=');
        }
    }

    public function testArabicLettersAreShapedInTheirWords()
    {
        // مرحبا: initial meem, final reh, initial hah, medial beh, final alef, drawn from the right
        $this->assertSame("\u{FE8E}\u{FE92}\u{FEA3}\u{FEAE}\u{FEE3}", Bidi::reorderLine('مرحبا', 1));
        $this->assertSame("\u{FEE1} \u{FE8F}", Bidi::reorderLine('ب م', 1), 'Letters alone are isolated.');
        $this->assertSame("\u{FEAA}\u{FE91}", Bidi::reorderLine('بد', 1), 'A letter that joins the previous one only.');
    }

    public function testOpenControls()
    {
        $this->assertSame('', Bidi::openControls('a b'));
        $this->assertSame("\u{2067}\u{202E}", Bidi::openControls("a \u{2067}\u{202E}b"));
        $this->assertSame("\u{2067}", Bidi::openControls("\u{2067}\u{202B}b\u{202C}"));
        $this->assertSame('', Bidi::openControls("\u{2067}\u{202B}b\u{2069}"), 'A PDI closes the embeddings inside its isolate.');
    }
}
