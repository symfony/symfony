<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\Tests\Transliterator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Intl\Transliterator\EmojiTransliterator;

/**
 * @requires extension intl
 */
final class EmojiTransliteratorTest extends TestCase
{
    public function provideTransliterateTests(): iterable
    {
        yield [
            'fr',
            'un 😺, 🐈‍⬛, et a 🦁 vont au 🏞️',
            'un chat qui sourit, chat noir, et a tête de lion vont au parc national️',
        ];
        yield [
            'en',
            'a 😺, 🐈‍⬛, and a 🦁 go to 🏞️... 😍 🎉 💛',
            'a grinning cat, black cat, and a lion go to national park️... smiling face with heart-eyes party popper yellow heart',
        ];

        $specialArrowInput = '↔ - ↔️'; // The first arrow is particularly problematic!
        yield [
            'en',
            $specialArrowInput,
            'left-right arrow - left-right arrow️',
        ];
        yield [
            'fr',
            $specialArrowInput,
            'flèche gauche droite - flèche gauche droite️',
        ];
    }

    /** @dataProvider provideTransliterateTests */
    public function testTransliterate(string $locale, string $input, string $expected)
    {
        $tr = EmojiTransliterator::getInstance($locale);

        $this->assertSame($expected, $tr->transliterate($input));
    }

    public function testTransliteratorCache()
    {
        $tr1 = EmojiTransliterator::getInstance('en');
        $tr2 = EmojiTransliterator::getInstance('en');

        $this->assertSame($tr1, $tr2);
    }

    public function provideLocaleTest(): iterable
    {
        $file = (new Finder())
            ->in(__DIR__.'/../../Resources/data/transliterator/emoji')
            ->name('*.txt')
            ->files()
        ;

        foreach ($file as $file) {
            yield [$file->getBasename('.txt')];
        }
    }

    /** @dataProvider provideLocaleTest */
    public function testAllTransliterator(string $locale)
    {
        $tr = EmojiTransliterator::getInstance($locale);

        $this->assertNotEmpty($tr->transliterate('😀'));
    }

    public function testTransliterateWithInvalidLocale()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "../emoji/en" locale.');

        EmojiTransliterator::getInstance('../emoji/en');
    }

    public function testTransliterateWithMissingLocale()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The transliterator rules source does not exist for locale "invalid".');

        EmojiTransliterator::getInstance('invalid');
    }

    public function testTransliterateWithBrokenLocale()
    {
        $brokenFilename = __DIR__.'/../../Resources/data/transliterator/emoji/broken.txt';
        file_put_contents($brokenFilename, '😀 > oups\' ;');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to create EmojiTransliterator instance: "transliterator_create_from_rules: unable to create ICU transliterator from rules (parse error at offset 4, after "😀 >", before or at " oups\' ;"): U_UNTERMINATED_QUOTE".');

        try {
            EmojiTransliterator::getInstance('broken');
        } finally {
            unlink($brokenFilename);
        }
    }
}
