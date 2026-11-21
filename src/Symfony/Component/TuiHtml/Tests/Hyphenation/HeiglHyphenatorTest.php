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

use Org\Heigl\Hyphenator\Hyphenator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresMethod;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Hyphenation\HeiglHyphenator;

#[RequiresMethod(Hyphenator::class, 'hyphenate')]
final class HeiglHyphenatorTest extends TestCase
{
    #[DataProvider('provideWords')]
    public function testWordsBreakInTheirLanguage(string $language, string $word, string $hyphenated)
    {
        $this->assertSame($hyphenated, self::show($word, (new HeiglHyphenator())->hyphenate($word, $language)));
    }

    public static function provideWords(): iterable
    {
        yield 'English' => ['en', 'hyphenation', 'hy-phen-ation'];
        yield 'English, its region' => ['en-us', 'representation', 'rep-re-sen-ta-tion'];
        yield 'German, umlauts' => ['de', 'Änderungsmöglichkeiten', 'Än-de-rungs-mög-lich-kei-ten'];
        yield 'German, sharp s' => ['de-de', 'Straßenbahnhaltestelle', 'Stra-ßen-bahn-hal-te-stel-le'];
        yield 'German of Switzerland' => ['de-ch', 'Silbentrennung', 'Sil-ben-tren-nung'];
        yield 'French, accents' => ['fr', 'développement', 'dé-ve-lop-pe-ment'];
        yield 'French of Canada, the French dictionary' => ['fr-ca', 'électricité', 'élec-tri-ci-té'];
        yield 'Spanish' => ['es', 'responsabilidad', 'res-pon-sa-bi-li-dad'];
        yield 'Spanish, diaeresis' => ['es', 'pingüino', 'pin-güino'];
        yield 'Czech, its only dictionary of another region' => ['cs', 'nejneobhospodařovávatelnějšími', 'nej-ne-ob-hos-po-da-řo-vá-va-tel-něj-ší-mi'];
    }

    public function testALanguageWithoutADictionaryIsNotHyphenated()
    {
        $hyphenator = new HeiglHyphenator();

        $this->assertSame([], $hyphenator->hyphenate('hyphenation', 'xx'));
        $this->assertSame([], $hyphenator->hyphenate('hyphenation', 'tlh-latn'));
        $this->assertSame([], $hyphenator->hyphenate('hyphenation', ''));
    }

    public function testAMissingDictionaryDirectoryHyphenatesNothing()
    {
        $hyphenator = new HeiglHyphenator(sys_get_temp_dir().'/no-hyphenation-dictionaries-'.bin2hex(random_bytes(4)));

        $this->assertSame([], $hyphenator->getLocales());
        $this->assertSame([], $hyphenator->hyphenate('hyphenation', 'en'));
    }

    public function testShortWordsAreNotBroken()
    {
        $this->assertSame([], (new HeiglHyphenator())->hyphenate('a', 'en'));
        $this->assertSame([], (new HeiglHyphenator())->hyphenate('', 'en'));
    }

    public function testTheLocalesAreListed()
    {
        $locales = (new HeiglHyphenator())->getLocales();

        foreach (['de_DE', 'en_US', 'fr', 'es'] as $locale) {
            $this->assertContains($locale, $locales);
        }
    }

    public function testItHyphenatesAPage()
    {
        $view = (new HtmlBuilder(new CssStyleSheet(hyphenator: new HeiglHyphenator())))->build('<p id="p" lang="en" style="hyphens: auto">Representation</p>');
        $view->computeStyles();

        $this->assertSame("Rep\u{AD}re\u{AD}sen\u{AD}ta\u{AD}tion", $view->getById('p')->getText());
    }

    /**
     * @param list<int> $breaks
     */
    private static function show(string $word, array $breaks): string
    {
        $shown = '';
        foreach (mb_str_split($word) as $i => $character) {
            $shown .= (\in_array($i, $breaks, true) ? '-' : '').$character;
        }

        return $shown;
    }
}
