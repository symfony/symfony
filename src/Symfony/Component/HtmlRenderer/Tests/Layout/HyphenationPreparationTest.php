<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Layout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Hyphenation\HyphenatorInterface;
use Symfony\Component\HtmlRenderer\Hyphenation\LanguageHyphenator;
use Symfony\Component\HtmlRenderer\Text\Hyphenation;
use Symfony\Component\HtmlRenderer\Text\TextRun;

final class HyphenationPreparationTest extends TestCase
{
    public function testDictionaryBreaksAreBoundedSortedUniqueGraphemeBoundariesAndCached()
    {
        $dictionary = new class implements HyphenatorInterface {
            public int $calls = 0;

            public function hyphenate(string $word, string $language): array
            {
                ++$this->calls;

                return [-1, 0, 1, 2, 4, 3, 2, 99, '2'];
            }
        };
        $hyphenator = new LanguageHyphenator($dictionary, 'fr');
        self::assertSame([2, 3], $hyphenator->hyphenate("e\u{0301}té"));
        self::assertSame([2, 3], $hyphenator->hyphenate("e\u{0301}té"));
        self::assertSame(1, $dictionary->calls);
        self::assertSame([], $hyphenator->hyphenate(str_repeat('a', 101)));
        self::assertSame(1, $dictionary->calls);
    }

    public function testAddressesAndManualBreaksDoNotAskTheDictionary()
    {
        $asked = [];
        $lookup = static function (string $word) use (&$asked): array {
            $asked[] = $word;

            return [2];
        };
        $text = "https://example.com/abcd mail@abcd.fr ab\u{00AD}cd abcd";
        self::assertSame("https://example.com/abcd mail@abcd.fr ab\u{00AD}cd ab\u{00AD}cd", Hyphenation::prepare($text, $lookup));
        self::assertSame(['abcd'], $asked);
        self::assertSame("éab\u{00AD}cd", Hyphenation::prepare("é\u{00AD}ab\u{00AD}cd", null, true, [5, 2, 2]));
        self::assertSame('éabcd', Hyphenation::prepare("é\u{00AD}ab\u{00AD}cd", $lookup, false));
        self::assertSame(['abcd'], $asked);
    }

    public function testInsertedHyphensAndSlicesKeepOriginalSourceOffsets()
    {
        $cursor = 0;
        $first = TextRun::fromPreparedLine(['a', 'b', 'c', 'd'], "ab\u{00AD}", 'ab==', $cursor, null);
        self::assertSame([0, 1, 2, 2, 2], $first->sourceOffsets);
        self::assertSame(4, $first->toDisplay(2));
        self::assertSame(2, $first->toSource(4));
        $second = TextRun::fromPreparedLine(['a', 'b', 'c', 'd'], 'cd', 'cd', $cursor, null);
        self::assertSame([2, 3, 4], $second->sourceOffsets);
        self::assertSame([3, 4], $second->slice(1, 1)->sourceOffsets);
        self::assertSame(4, $cursor);
    }
}
