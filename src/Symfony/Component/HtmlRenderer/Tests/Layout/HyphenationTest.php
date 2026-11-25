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
use Symfony\Component\HtmlRenderer\Event\KeyboardEvent;
use Symfony\Component\HtmlRenderer\Font\FontStyle;
use Symfony\Component\HtmlRenderer\Font\TextMeasurerInterface;
use Symfony\Component\HtmlRenderer\Font\TextMetrics;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Hyphenation\HyphenatorInterface;
use Symfony\Component\HtmlRenderer\RenderingContext;

final class HyphenationTest extends TestCase
{
    public function testDiscretionaryHyphenOnlyAppearsAtTheChosenBreak()
    {
        self::assertSame(['ab-', 'cd'], $this->lines('ab&shy;cd', 'width:24px'));
        self::assertSame(['abcd'], $this->lines('ab&shy;cd', 'width:32px'));
        self::assertSame(['abcd'], $this->lines('ab&shy;cd', 'width:24px;hyphens:none'));
        self::assertSame(['abcd'], $this->lines('ab&shy;cd', 'width:24px;white-space:nowrap'));
    }

    public function testUsesRemainingLineSpaceAndCustomHyphen()
    {
        self::assertSame(['X ab-', 'cdef'], $this->lines('X ab&shy;cdef', 'width:40px'));
        self::assertSame(['ab=', 'cd'], $this->lines('ab&shy;cd', 'width:24px;hyphenate-character:"="'));
        self::assertSame(['abcd'], $this->lines('ab&shy;cd', 'width:24px;hyphenate-limit-chars:5'));
    }

    public function testInjectedDictionaryUsesInheritedLanguageAndPreservesTheDom()
    {
        $dictionary = new class implements HyphenatorInterface {
            public array $calls = [];

            public function hyphenate(string $word, string $language): array
            {
                $this->calls[] = [$word, $language];

                return [2];
            }
        };
        $context = new RenderingContext(100, 100, null, null, null, null, [], '', [], $dictionary);
        $result = (new HtmlRenderer())->render('<style>p{width:24px;font-size:16px;line-height:16px;hyphens:auto}</style><section lang="FR"><p>abcd</p></section>', $context);
        self::assertSame(['ab-', 'cd'], array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text'));
        self::assertSame('abcd', $result->getDocument()->querySelector('p')->textContent);
        self::assertSame([['abcd', 'fr']], $dictionary->calls);
        self::assertSame($dictionary, $context->withViewport(120, 80)->hyphenator);
    }

    public function testFractionalProportionalMeasurementsChooseTheBreak()
    {
        $measurer = new class implements TextMeasurerInterface {
            public function measure(string $text, FontStyle $font): TextMetrics
            {
                $width = 0.0;
                foreach (mb_str_split($text) as $character) {
                    $width += match ($character) {
                        'W' => 10.5, 'i' => 2.25, '-' => 3.5, default => 0.0,
                    };
                }

                return new TextMetrics($width, 12, 4);
            }
        };
        $result = (new HtmlRenderer())->render('<p style="width:16.25px;font-size:16px;line-height:16px">Wi&shy;Wi</p>', new RenderingContext(100, 100, $measurer));
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']));
        self::assertSame(['Wi-', 'Wi'], array_column($text, 'text'));
        self::assertSame([16.25, 12.75], array_column($text, 'width'));
    }

    public function testHiddenSoftHyphensPreserveEditingCaretSelectionAndHitPositions()
    {
        $session = (new HtmlRenderer())->createSession('<input style="width:100px;padding:0;border:0;font-size:16px" value="ab&shy;cd">', new RenderingContext(100, 100));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $session->key(new KeyboardEvent('End'));
        $result = $session->render();
        $carets = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'caret' === $op['type']));
        self::assertCount(1, $carets);
        self::assertSame(32.0, $carets[0]['x']);
        self::assertSame([5, 5], $session->getTextSelection($input));
        self::assertSame(5, $result->getTextPosition($input, 32, 8));
        self::assertSame(3, $result->getTextPosition($input, 16, 8));
        $session->setTextSelection($input, 0, 5);
        $rectangles = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op): bool => 'rectangle' === $op['type']));
        self::assertContains(32.0, array_column($rectangles, 'width'));
        self::assertSame("ab\u{00AD}cd", $session->getValue($input));
    }

    public function testChosenManualHyphenRetainsItsBeforeAndAfterSourceBoundaries()
    {
        $session = (new HtmlRenderer())->createSession('<textarea style="width:24px;height:64px;padding:0;border:0;font-size:16px;line-height:16px">ab&shy;cd</textarea>', new RenderingContext(100, 100));
        $input = $session->getDocument()->querySelector('textarea');
        $session->focus($input);
        $session->setTextSelection($input, 2, 2);
        $carets = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op): bool => 'caret' === $op['type']));
        self::assertCount(1, $carets);
        self::assertSame([16.0, 0.0], [$carets[0]['x'], $carets[0]['y']]);
        $session->setTextSelection($input, 3, 3);
        $result = $session->render();
        $carets = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'caret' === $op['type']));
        self::assertCount(1, $carets);
        self::assertSame([0.0, 16.0], [$carets[0]['x'], $carets[0]['y']]);
        self::assertSame(2, $result->getTextPosition($input, 16, 8));
        self::assertSame(3, $result->getTextPosition($input, 23.99, 8));
    }

    public function testHiddenHyphensKeepBlankLineSourcePositions()
    {
        $session = (new HtmlRenderer())->createSession("<textarea style=\"width:100px;height:80px;padding:0;border:0;font-size:16px;line-height:16px\">a&shy;b\n\nc</textarea>", new RenderingContext(100, 100));
        $input = $session->getDocument()->querySelector('textarea');
        $session->focus($input);
        $session->setTextSelection($input, 4, 4);
        $carets = array_values(array_filter($session->render()->getDisplayList()->getOperations(), static fn (array $op): bool => 'caret' === $op['type']));
        self::assertCount(1, $carets);
        self::assertSame([0.0, 16.0], [$carets[0]['x'], $carets[0]['y']]);
    }

    private function lines(string $text, string $style): array
    {
        $result = (new HtmlRenderer())->render('<p style="margin:0;font-size:16px;line-height:16px;'.htmlspecialchars($style).'">'.$text.'</p>', new RenderingContext(100, 100));

        return array_values(array_column(array_filter($result->getDisplayList()->getOperations(), static fn (array $op): bool => 'text' === $op['type']), 'text'));
    }
}
