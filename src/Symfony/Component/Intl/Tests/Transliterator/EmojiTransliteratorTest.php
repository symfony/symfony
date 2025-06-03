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
class EmojiTransliteratorTest extends TestCase
{
    /**
     * @dataProvider provideTransliterateTests
     */
    public function testTransliterate(string $locale, string $input, string $expected)
    {
        $tr = EmojiTransliterator::create('emoji-'.$locale);

        $this->assertSame($expected, $tr->transliterate($input));
    }

    public static function provideTransliterateTests(): iterable
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
        yield [
            'github',
            $specialArrowInput,
            ':left_right_arrow: - :left_right_arrow:️',
        ];
        yield [
            'slack',
            $specialArrowInput,
            '↔ - :left_right_arrow:',
        ];

        yield [
            'strip',
            'un 😺, 🐈‍⬛, et a 🦁 vont au 🏞️ étoile',
            'un , , et a  vont au  étoile',
        ];
        yield [
            'strip',
            'a 😺, 🐈‍⬛, and a 🦁 go to 🏞️... 😍 🎉 💛',
            'a , , and a  go to ...   ',
        ];
        yield [
            'strip',
            $specialArrowInput,
            ' - ',
        ];
    }

    /**
     * @dataProvider provideLocaleTest
     */
    public function testAllTransliterator(string $locale)
    {
        $tr = EmojiTransliterator::create($locale);

        $this->assertNotEmpty($tr->transliterate('😀'));
    }

    public static function provideLocaleTest(): iterable
    {
        $file = (new Finder())
            ->in(__DIR__.'/../../Resources/data/transliterator/emoji')
            ->name('*.php')
            ->notName('emoji-strip.php')
            ->files()
        ;

        foreach ($file as $file) {
            yield [$file->getBasename('.php')];
        }
    }

    public function testTransliterateWithInvalidLocale()
    {
        $this->expectException(\IntlException::class);
        $this->expectExceptionMessage('transliterator_create: unable to open ICU transliterator with id "emoji-invalid"');

        EmojiTransliterator::create('invalid');
    }

    public function testListIds()
    {
        $this->assertContains('emoji-en_ca', EmojiTransliterator::listIDs());
        $this->assertNotContains('..', EmojiTransliterator::listIDs());
    }

    public function testSlice()
    {
        $tr = EmojiTransliterator::create('emoji-en');
        $this->assertSame('😀grinning face', $tr->transliterate('😀😀', 2));
    }

    public function testNotUtf8()
    {
        $tr = EmojiTransliterator::create('emoji-en');

        $this->iniSet('intl.use_exceptions', 0);

        $this->assertFalse($tr->transliterate("Not \xE9 UTF-8"));
        $this->assertSame('String conversion of string to UTF-16 failed: U_INVALID_CHAR_FOUND', intl_get_error_message());

        $this->iniSet('intl.use_exceptions', 1);

        $this->expectException(\IntlException::class);
        $this->expectExceptionMessage('String conversion of string to UTF-16 failed');

        $tr->transliterate("Not \xE9 UTF-8");
    }

    public function testBadOffsets()
    {
        $tr = EmojiTransliterator::create('emoji-en');

        $this->iniSet('intl.use_exceptions', 0);

        $this->assertFalse($tr->transliterate('Abc', 1, 5));
        $this->assertSame('transliterator_transliterate: Neither "start" nor the "end" arguments can exceed the number of UTF-16 code units (in this case, 3): U_ILLEGAL_ARGUMENT_ERROR', intl_get_error_message());

        $this->iniSet('intl.use_exceptions', 1);

        $this->expectException(\IntlException::class);
        $this->expectExceptionMessage('transliterator_transliterate: Neither "start" nor the "end" arguments can exceed the number of UTF-16 code units (in this case, 3)');

        $this->assertFalse($tr->transliterate('Abc', 1, 5));
    }

    public function testReverse()
    {
        $tr = EmojiTransliterator::create('emoji-github', EmojiTransliterator::REVERSE);
        $this->assertSame('github-emoji', $tr->id);
        $this->assertSame('🎉', $tr->transliterate(':tada:'));

        $tr = EmojiTransliterator::create('emoji-slack');
        $this->assertSame('emoji-slack', $tr->id);
        $this->assertSame(':tada:', $tr->transliterate('🎉'));

        $tr = $tr->createInverse();
        $this->assertSame('slack-emoji', $tr->id);
        $this->assertSame('🎉', $tr->transliterate(':tada:'));

        $this->expectException(\IntlException::class);
        EmojiTransliterator::create('emoji-en', EmojiTransliterator::REVERSE);
    }
}
