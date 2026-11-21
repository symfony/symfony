<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Hyphenation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Hyphenation\HyphenatorInterface;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Tests\Fixtures\WordListHyphenator;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;

/**
 * hyphens: auto with and without a hyphenator, through what is drawn.
 */
final class HyphenationTest extends TestCase
{
    private const PARAGRAPH = '<p lang="en" style="hyphens: auto">Automatic hyphenation breaks extraordinarily long words.</p>';

    public function testWordsWrapWholeWithoutAHyphenator()
    {
        $this->assertSame(['Automatic', 'hyphenation', 'breaks', 'extraordinarily', 'long words.'], $this->draw(self::PARAGRAPH, 15));
    }

    #[DataProvider('provideWidths')]
    public function testAHyphenatorBreaksTheWordsOfTheLanguage(int $columns, array $lines)
    {
        $this->assertSame($lines, $this->draw(self::PARAGRAPH, $columns, WordListHyphenator::forFixtures()));
    }

    public static function provideWidths(): iterable
    {
        yield 'narrow' => [10, ['Automatic', 'hyphen-', 'ation', 'breaks ex-', 'traordi-', 'narily', 'long', 'words.']];
        yield 'medium' => [40, ['Automatic hyphenation breaks extraordi-', 'narily long words.']];
        yield 'wide' => [80, ['Automatic hyphenation breaks extraordinarily long words.']];
    }

    #[DataProvider('provideHyphensValues')]
    public function testOnlyAutoAsksTheHyphenator(string $hyphens, array $lines)
    {
        $hyphenator = WordListHyphenator::forFixtures();
        $html = '<p lang="en" style="hyphens: '.$hyphens.'">Automatic hyphenation breaks super&shy;califragilistic words.</p>';

        $this->assertSame($lines, $this->draw($html, 12, $hyphenator));
        if ('auto' !== $hyphens) {
            $this->assertSame([], $hyphenator->asked);
        }
    }

    public static function provideHyphensValues(): iterable
    {
        yield 'auto: a word with soft hyphens broken there only' => ['auto', ['Automatic', 'hyphenation', 'breaks', 'super-', 'califragilis', 'tic words.']];
        yield 'manual: the soft hyphens only' => ['manual', ['Automatic', 'hyphenation', 'breaks', 'super-', 'califragilis', 'tic words.']];
        yield 'none: no break, the soft hyphens ignored' => ['none', ['Automatic', 'hyphenation', 'breaks', 'supercalifra', 'gilistic', 'words.']];
    }

    public function testATextWithoutALanguageOrADictionaryWrapsWhole()
    {
        $hyphenator = WordListHyphenator::forFixtures();
        $lines = ['Automatic', 'hyphenation', 'breaks', 'extraordinarily', 'long words.'];

        $this->assertSame($lines, $this->draw('<p style="hyphens: auto">Automatic hyphenation breaks extraordinarily long words.</p>', 15, $hyphenator));
        $this->assertSame([], $hyphenator->asked, 'Not without a language.');
        $this->assertSame($lines, $this->draw('<p lang="nl" style="hyphens: auto">Automatic hyphenation breaks extraordinarily long words.</p>', 15, $hyphenator));
        $this->assertContains(['extraordinarily', 'nl'], $hyphenator->asked, 'Asked, a language it has no dictionary for.');
    }

    public function testTheLanguageIsInheritedAndTaggedInLowercase()
    {
        $hyphenator = WordListHyphenator::forFixtures();
        $this->draw('<div lang="de-CH"><p style="hyphens: auto">Änderungsmöglichkeiten <span lang="FR">développement <b lang="en">hyphenation</b> informatique</span> Silbentrennung</p></div>', 12, $hyphenator);

        // Each word in the language of its element, not of the last element before it
        $this->assertEqualsCanonicalizing([
            ['Änderungsmöglichkeiten', 'de-ch'], ['Silbentrennung', 'de-ch'],
            ['développement', 'fr'], ['informatique', 'fr'],
            ['hyphenation', 'en'],
        ], $hyphenator->asked);
    }

    public function testAccentedWordsBreakBetweenCharacters()
    {
        $html = '<p lang="de" style="hyphens: auto">Änderungsmöglichkeiten</p><p lang="fr" style="hyphens: auto">développement</p>';

        $this->assertSame(['Änderungs-', 'möglichkei-', 'ten', 'développe-', 'ment'], $this->draw($html, 11, WordListHyphenator::forFixtures()));
    }

    public function testAddressesAndOverlongWordsAreNotHyphenated()
    {
        $hyphenator = WordListHyphenator::forFixtures();
        $long = str_repeat('a', 120);
        $this->draw('<p lang="en" style="hyphens: auto">https://example.com/extraordinarily www.example.org/hyphenation mailto:someone@example.com someone@extraordinarily.example (see:https://example.com/hyphenation) josé@extraordinarily.example '.$long.' example</p>', 40, $hyphenator);

        $this->assertSame([['example', 'en']], $hyphenator->asked, 'Only the word on its own.');
    }

    public function testBreaksOutsideTheWordAreIgnored()
    {
        $hyphenator = new class implements HyphenatorInterface {
            public function hyphenate(string $word, string $language): array
            {
                return [-1, 0, 99, 5, 5, 3];
            }
        };

        $this->assertSame(['Wonde-', 'rful'], $this->draw('<p lang="en" style="hyphens: auto">Wonderful</p>', 6, $hyphenator));
    }

    public function testTheDocumentIsNotChanged()
    {
        $view = $this->build('<p id="p" lang="en" style="hyphens: auto">Automatic hyphenation breaks extraordinarily long words.</p>', WordListHyphenator::forFixtures());
        $this->drawView($view, 10);

        // The text found by highlights and fragments; what is drawn has the soft hyphens
        $text = $view->querySelector('#p');
        $this->assertInstanceOf(RichTextWidget::class, $text);
        $this->assertSame('Automatic hyphenation breaks extraordinarily long words.', $text->getSourceText());
        $this->assertStringContainsString("ex\u{AD}tra", $text->getText());
    }

    public function testTheHyphenatorIsAskedOnceAWordAndLanguage()
    {
        $hyphenator = WordListHyphenator::forFixtures();
        $view = $this->build('<p lang="en" style="hyphens: auto">hyphenation hyphenation</p><p lang="en" style="hyphens: auto">hyphenation</p>', $hyphenator);
        foreach ([10, 20, 10] as $columns) {
            $this->drawView($view, $columns);
        }

        $this->assertSame([['hyphenation', 'en']], $hyphenator->asked);
    }

    public function testFramesUseTheHyphenatorOfTheirDocument()
    {
        $hyphenator = WordListHyphenator::forFixtures();
        $nested = (new HtmlBuilder(new CssStyleSheet(hyphenator: $hyphenator)))->buildFrame('<p lang="en" style="hyphens: auto">hyphenation</p>');
        $this->drawView($nested, 8);

        $this->assertSame([['hyphenation', 'en']], $hyphenator->asked);
    }

    /**
     * @return list<string>
     */
    private function draw(string $html, int $columns, ?HyphenatorInterface $hyphenator = null): array
    {
        return $this->drawView($this->build($html, $hyphenator), $columns);
    }

    private function build(string $html, ?HyphenatorInterface $hyphenator): HtmlView
    {
        $builder = new HtmlBuilder(new CssStyleSheet(hyphenator: $hyphenator));
        $builder->setDefaultStyles(false);

        return $builder->build($html);
    }

    /**
     * @return list<string>
     */
    private function drawView(HtmlView $view, int $columns): array
    {
        $terminal = new VirtualTerminal($columns, 20);
        $tui = new Tui(null, $terminal);
        $view->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->stop();
        $view->unmount();
        $screen = new ScreenBuffer($columns, 20);
        $screen->write($terminal->getOutput());

        return array_values(array_filter(array_map(rtrim(...), explode("\n", $screen->getScreen())), static fn (string $line) => '' !== $line));
    }
}
