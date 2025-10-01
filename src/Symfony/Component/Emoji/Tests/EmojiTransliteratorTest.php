<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Emoji\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Emoji\EmojiTransliterator;
use Symfony\Component\Finder\Finder;

#[RequiresPhpExtension('intl')]
class EmojiTransliteratorTest extends TestCase
{
    #[DataProvider('provideTransliterateTests')]
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
            'gitlab',
            '🤼',
            ':wrestlers:',
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

    #[DataProvider('provideLocaleTest')]
    public function testAllTransliterator(string $locale)
    {
        $tr = EmojiTransliterator::create($locale);

        $this->assertNotEmpty($tr->transliterate('😀'));
    }

    public static function provideLocaleTest(): iterable
    {
        $file = (new Finder())
            ->in(__DIR__.'/../Resources/data')
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

        $this->expectExceptionMessage(\sprintf('%s: unable to open ICU transliterator with id "emoji-invalid":', \PHP_VERSION_ID >= 80500 ? 'Transliterator::create()' : 'transliterator_create'));

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

        $oldUseExceptionsValue = ini_set('intl.use_exceptions', 0);

        try {
            $this->assertFalse($tr->transliterate("Not \xE9 UTF-8"));
            $this->assertSame(\sprintf('%sString conversion of string to UTF-16 failed: U_INVALID_CHAR_FOUND', \PHP_VERSION_ID >= 80500 ? 'Transliterator::transliterate(): ' : ''), intl_get_error_message());

            ini_set('intl.use_exceptions', 1);

            $this->expectException(\IntlException::class);
            $this->expectExceptionMessage('String conversion of string to UTF-16 failed');

            $tr->transliterate("Not \xE9 UTF-8");
        } finally {
            ini_set('intl.use_exceptions', $oldUseExceptionsValue);
        }
    }

    public function testBadOffsets()
    {
        $tr = EmojiTransliterator::create('emoji-en');

        $oldUseExceptionsValue = ini_set('intl.use_exceptions', 0);

        try {
            $this->assertFalse($tr->transliterate('Abc', 1, 5));
            $this->assertSame(\sprintf('%s: Neither "start" nor the "end" arguments can exceed the number of UTF-16 code units (in this case, 3): U_ILLEGAL_ARGUMENT_ERROR', \PHP_VERSION_ID >= 80500 ? 'Transliterator::transliterate()' : 'transliterator_transliterate'), intl_get_error_message());

            ini_set('intl.use_exceptions', 1);

            $this->expectException(\IntlException::class);
            $this->expectExceptionMessage(\sprintf('%s: Neither "start" nor the "end" arguments can exceed the number of UTF-16 code units (in this case, 3)', \PHP_VERSION_ID >= 80500 ? 'Transliterator::transliterate()' : 'transliterator_transliterate'));

            $this->assertFalse($tr->transliterate('Abc', 1, 5));
        } finally {
            ini_set('intl.use_exceptions', $oldUseExceptionsValue);
        }
    }

    public function testReverse()
    {
        $tr = EmojiTransliterator::create('emoji-github', EmojiTransliterator::REVERSE);
        $this->assertSame('github-emoji', $tr->id);
        $this->assertSame('🎉', $tr->transliterate(':tada:'));

        $tr = EmojiTransliterator::create('emoji-gitlab', EmojiTransliterator::REVERSE);
        $this->assertSame('gitlab-emoji', $tr->id);
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

    public function testGetErrorCodeWithUninitializedTransliterator()
    {
        $transliterator = EmojiTransliterator::create('emoji-en');

        $this->assertSame(0, $transliterator->getErrorCode());
    }

    public function testGetErrorMessageWithUninitializedTransliterator()
    {
        $transliterator = EmojiTransliterator::create('emoji-en');

        $this->assertSame('', $transliterator->getErrorMessage());
    }
}
