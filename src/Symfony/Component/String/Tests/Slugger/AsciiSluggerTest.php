<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests\Slugger;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class AsciiSluggerTest extends TestCase
{
    #[DataProvider('provideSlugTests')]
    public function testSlug(string $expected, string $string, string $separator = '-', ?string $locale = null)
    {
        $slugger = new AsciiSlugger();

        $this->assertSame($expected, (string) $slugger->slug($string, $separator, $locale));
    }

    public function testSluggingParentLocaleDoesNotDependOnPreviousCalls()
    {
        if (!\function_exists('transliterator_transliterate')) {
            $this->markTestSkipped('The intl extension is required.');
        }

        $slugger = new AsciiSlugger();

        $this->assertSame('g', (string) $slugger->slug('ғ', '-', 'uz_Latn_UZ'));
        $this->assertSame('gh', (string) $slugger->slug('ғ', '-', 'uz_Latn'));
        $this->assertSame('g', (string) $slugger->slug('ғ', '-', 'uz_Latn_UZ'));
        $this->assertSame('gh', (string) $slugger->slug('ғ', '-', 'uz_Latn'));
    }

    public static function provideSlugTests(): iterable
    {
        yield ['', ''];
        yield ['foo', ' foo '];
        yield ['foo-bar', 'foo bar'];

        yield ['foo-bar', 'foo@bar', '-'];
        yield ['foo-at-bar', 'foo@bar', '-', 'en'];

        yield ['e-a', 'é$!à'];
        yield ['e_a', 'é$!à', '_'];

        yield ['a', 'ä'];
        yield ['a', 'ä', '-', 'fr'];
        yield ['ae', 'ä', '-', 'de'];
        yield ['ae', 'ä', '-', 'de_fr']; // Ensure we get the parent locale
        yield ['ae', 'ä', '-', 'de-FR'];
        yield [\function_exists('transliterator_transliterate') ? 'g' : '', 'ғ', '-'];
        yield [\function_exists('transliterator_transliterate') ? 'gh' : '', 'ғ', '-', 'uz'];
        yield [\function_exists('transliterator_transliterate') ? 'gh' : '', 'ғ', '-', 'uz_fr']; // Ensure we get the parent locale
    }

    public function testHyphenatedLocaleUsesParentTransliterator()
    {
        $slugger = new AsciiSlugger();

        $this->assertSame((string) $slugger->slug('ғ', '-', 'uz_fr'), (string) $slugger->slug('ғ', '-', 'uz-FR'));
    }

    public function testParentSymbolsMapPreservesHyphens()
    {
        $slugger = new AsciiSlugger(null, ['en-US' => ['@' => 'custom']]);

        $this->assertSame('foo-custom-bar', (string) $slugger->slug('foo@bar', '-', 'en-US_POSIX'));
    }

    public function testHyphenatedParentSymbolsMap()
    {
        $slugger = new AsciiSlugger(null, ['en-US' => ['@' => 'custom']]);

        $this->assertSame('foo-custom-bar', (string) $slugger->slug('foo@bar', '-', 'en-US-POSIX'));
    }

    public function testHyphenatedLocaleUsesScriptTransliterator()
    {
        $slugger = new AsciiSlugger();
        $expected = (string) $slugger->slug('љђ', '-', 'sr_Cyrl');

        $this->assertSame($expected, (string) $slugger->slug('љђ', '-', 'sr-Cyrl'));
        $this->assertSame($expected, (string) $slugger->slug('љђ', '-', 'sr-Cyrl-RS'));
    }

    #[RequiresPhpExtension('intl')]
    public function testHyphenatedLocaleUsesParentEmojiTransliterator()
    {
        $slugger = (new AsciiSlugger())->withEmoji();

        $this->assertSame((string) $slugger->slug('😺', '-', 'zh_Hant_HK'), (string) $slugger->slug('😺', '-', 'zh-Hant-HK'));
    }

    public function testHyphenatedArabicLocaleUsesParentSymbolsMap()
    {
        $slugger = new AsciiSlugger(null, ['ar' => ['@' => 'at']]);

        $this->assertSame('foo-at-bar', (string) $slugger->slug('foo@bar', '-', 'ar-TN'));
    }

    #[RequiresPhpExtension('intl')]
    #[DataProvider('provideSlugEmojiTests')]
    public function testSlugEmoji(string $expected, string $string, ?string $locale, string|bool $emoji = true)
    {
        $slugger = new AsciiSlugger();
        $slugger = $slugger->withEmoji($emoji);

        $this->assertSame($expected, (string) $slugger->slug($string, '-', $locale));
    }

    public static function provideSlugEmojiTests(): iterable
    {
        yield [
            'un-chat-qui-sourit-chat-noir-et-un-tete-de-lion-vont-au-parc-national',
            'un 😺, 🐈‍⬛, et un 🦁 vont au 🏞️',
            'fr',
        ];
        yield [
            'a-grinning-cat-black-cat-and-a-lion-go-to-national-park-smiling-face-with-heart-eyes-party-popper-yellow-heart',
            'a 😺, 🐈‍⬛, and a 🦁 go to 🏞️... 😍 🎉 💛',
            'en',
        ];
        yield [
            'a-and-a-go-to',
            'a 😺, 🐈‍⬛, and a 🦁 go to 🏞️... 😍 🎉 💛',
            null,
        ];
        yield [
            'a-smiley-cat-black-cat-and-a-lion-face-go-to-national-park-heart-eyes-tada-yellow-heart',
            'a 😺, 🐈‍⬛, and a 🦁 go to 🏞️... 😍 🎉 💛',
            null,
            'slack',
        ];
        yield [
            'a-smiley-cat-black-cat-and-a-lion-go-to-national-park-heart-eyes-tada-yellow-heart',
            'a 😺, 🐈‍⬛, and a 🦁 go to 🏞️... 😍 🎉 💛',
            null,
            'github',
        ];
        yield [
            'a-smiley-cat-black-cat-and-a-lion-go-to-national-park-heart-eyes-tada-yellow-heart',
            'a 😺, 🐈‍⬛, and a 🦁 go to 🏞️... 😍 🎉 💛',
            'en',
            'github',
        ];
        yield [
            'un-chat-qui-sourit-chat-noir-et-un-tete-de-lion-vont-au-parc-national',
            'un 😺, 🐈‍⬛, et un 🦁 vont au 🏞️',
            'fr_XX', // Fallback on parent locale
        ];
        yield [
            'un-et-un-vont-au',
            'un 😺, 🐈‍⬛, et un 🦁 vont au 🏞️',
            'undefined_locale', // Behaves the same as if emoji support is disabled
        ];
    }

    #[RequiresPhpExtension('intl')]
    public function testSlugEmojiWithSetLocale()
    {
        if (!setlocale(\LC_ALL, 'C.UTF-8')) {
            $this->markTestSkipped('Unable to switch to the "C.UTF-8" locale.');
        }

        $slugger = new AsciiSlugger();
        $slugger = $slugger->withEmoji(true);

        $this->assertSame('a-and-a-go-to', (string) $slugger->slug('a 😺, 🐈‍⬛, and a 🦁 go to 🏞️... 😍 🎉 💛', '-'));
    }

    public function testGetLocaleDefaultsToEmptyString()
    {
        $slugger = new AsciiSlugger();

        $this->assertSame('', $slugger->getLocale());

        $slugger->setLocale('en');
        $this->assertSame('en', $slugger->getLocale());
    }
}
