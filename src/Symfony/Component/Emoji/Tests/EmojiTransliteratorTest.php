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
use Symfony\Component\Emoji\Util\GzipStreamWrapper;
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

    #[DataProvider('provideStrtrTests')]
    public function testTransliterateGivesTheSameResultAsStrtr(string $id, string $input)
    {
        $map = self::loadMap($id);

        $this->assertSame(strtr($input, $map), EmojiTransliterator::create($id)->transliterate($input));
    }

    public static function provideStrtrTests(): iterable
    {
        $inputs = [
            'one emoji' => 'Great job 👍 see you',
            'adjacent emoji' => '👍👍🎉👍',
            'skin tones' => '👋🏽 👋 🏽 👍🏿👍',
            'families' => "👨\u{200D}👩\u{200D}👧\u{200D}👦👨\u{200D}👩\u{200D}👧 👨\u{200D}👩",
            'flags' => '🇪🇸🇫🇷 🇪 🇺🇳🇺',
            'tags' => "🏴\u{E0067}\u{E0062}\u{E0073}\u{E0063}\u{E0074}\u{E007F} 🏴",
            'keycaps' => "1\u{FE0F}\u{20E3} #\u{FE0F}\u{20E3}2\u{20E3} *\u{FE0F}\u{20E3} 10\u{FE0F}\u{20E3} 9 \u{FE0F}\u{20E3}",
            'text presentation' => "↔ ↔\u{FE0F} © ®\u{FE0F} ™",
            'no emoji' => "It’s a “great” day \u{2013} isn’t it… 10 € «oui»",
            'Japanese' => "今日はいい天気〰\u{FE0F}ですね〽",
            'shortcodes' => 'At 10:30 :thumbsup: :+1::tada: :tada:thumbsup: :not_a_shortcode: :',
            'dense' => str_repeat("👨\u{200D}👩\u{200D}👧\u{200D}👦 👍🏽 ", 20),
        ];

        foreach (['emoji-en', 'emoji-ja', 'emoji-strip', 'emoji-github', 'text-emoji', 'github-emoji', 'slack-emoji'] as $id) {
            foreach ($inputs as $name => $input) {
                yield "$id, $name" => [$id, $input];
            }
        }
    }

    #[DataProvider('provideAllIds')]
    public function testKeysHaveTheExpectedStructure(string $id)
    {
        $class = new \ReflectionClass(EmojiTransliterator::class);
        $maxKeyLength = $class->getConstant('MAX_KEY_LENGTH');
        $keyTailBytes = $class->getConstant('KEY_TAIL_BYTES');
        $sequencePattern = $class->getConstant('SEQUENCE_PATTERN');

        $map = self::loadMap($id);
        $shortcodes = str_starts_with(array_key_first($map), ':');
        $invalidKeys = [];

        foreach ($map as $key => $value) {
            $key = (string) $key;

            if ($shortcodes) {
                $valid = preg_match('/^:[^:]++:$/', $key);
            } else {
                $firstCharacterLength = \strlen(mb_substr($key, 0, 1));
                $valid = str_contains("\xC2\xE2\xE3\xF0#*0123456789", $key[0])
                    && \strlen($key) <= $maxKeyLength
                    && \strlen($key) - 1 === strspn($key, $keyTailBytes, 1)
                    && (\strlen($key) === $firstCharacterLength || preg_match($sequencePattern, $key, offset: $firstCharacterLength))
                    && (1 !== $firstCharacterLength || str_contains("\xE2\xEF", $key[1]));
            }

            if (!$valid) {
                $invalidKeys[] = $key;
            }
        }

        $this->assertSame([], $invalidKeys);
    }

    public static function provideAllIds(): iterable
    {
        foreach (EmojiTransliterator::listIDs() as $id) {
            yield [$id];
        }
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

    private static function loadMap(string $id): array
    {
        $file = __DIR__."/../Resources/data/{$id}.php";

        return is_file($file) ? require $file : GzipStreamWrapper::require($file.'.gz');
    }
}
