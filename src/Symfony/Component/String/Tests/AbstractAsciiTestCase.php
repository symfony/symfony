<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\String\AbstractString;
use Symfony\Component\String\ByteString;
use Symfony\Component\String\CodePointString;
use Symfony\Component\String\Exception\InvalidArgumentException;
use Symfony\Component\String\UnicodeString;

abstract class AbstractAsciiTestCase extends TestCase
{
    protected static function createFromString(string $string): AbstractString
    {
        throw new \BadMethodCallException('This method must be implemented by subclasses.');
    }

    public function testCreateFromString()
    {
        $bytes = self::createFromString('Symfony is a PHP framework!');

        self::assertSame('Symfony is a PHP framework!', (string) $bytes);
        self::assertSame(27, $bytes->length());
        self::assertFalse($bytes->isEmpty());
    }

    public function testCreateFromEmptyString()
    {
        $instance = self::createFromString('');

        self::assertSame('', (string) $instance);
        self::assertSame(0, $instance->length());
        self::assertTrue($instance->isEmpty());
    }

    /**
     * @dataProvider provideBytesAt
     */
    public function testBytesAt(array $expected, string $string, int $offset, int $form = null)
    {
        if (2 !== grapheme_strlen('च्छे') && 'नमस्ते' === $string) {
            self::markTestSkipped('Skipping due to issue ICU-21661.');
        }

        $instance = self::createFromString($string);
        $instance = $form ? $instance->normalize($form) : $instance;

        self::assertSame($expected, $instance->bytesAt($offset));
    }

    public static function provideBytesAt(): array
    {
        return [
            [[], '', 0],
            [[], 'a', 1],
            [[0x62], 'abc', 1],
            [[0x63], 'abcde', -3],
        ];
    }

    /**
     * @dataProvider provideIndexOf
     */
    public function testContainsAny(?int $result, string $string, $needle)
    {
        $instance = self::createFromString($string);

        self::assertSame(null !== $instance->indexOf($needle), $instance->containsAny($needle));
    }

    /**
     * @dataProvider provideIndexOfIgnoreCase
     */
    public function testContainsAnyIgnoreCase(?int $result, string $string, $needle)
    {
        $instance = self::createFromString($string);

        self::assertSame(null !== $instance->ignoreCase()->indexOf($needle), $instance->ignoreCase()->containsAny($needle));
    }

    public function testUnwrap()
    {
        $expected = ['hello', 'world'];

        $s = self::createFromString('');

        $actual = $s::unwrap([self::createFromString('hello'), self::createFromString('world')]);

        self::assertEquals($expected, $actual);
    }

    /**
     * @dataProvider wordwrapProvider
     */
    public function testWordwrap($expected, $actual, $length, $break, $cut = false)
    {
        $instance = self::createFromString($actual);
        $actual = $instance->wordwrap($length, $break, $cut);

        self::assertEquals($expected, $actual);
    }

    public function wordwrapProvider()
    {
        return [
            [
                'Lo-re-m-Ip-su-m',
                'Lorem Ipsum',
                2,
                '-',
                true,
            ],
            [
                'Lorem-Ipsum',
                'Lorem Ipsum',
                2,
                '-',
            ],
            [
                'Lor-em-Ips-um',
                'Lorem Ipsum',
                3,
                '-',
                true,
            ],
            [
                'L-o-r-e-m-I-p-s-u-m',
                'Lorem Ipsum',
                1,
                '-',
                true,
            ],
        ];
    }

    /**
     * @dataProvider provideWrap
     */
    public function testWrap(array $expected, array $values)
    {
        $s = self::createFromString('');

        self::assertEquals($expected, $s::wrap($values));
    }

    public static function provideWrap(): array
    {
        return [
            [[], []],
            [
                ['abc' => self::createFromString('foo'), 1, self::createFromString('bar'), 'baz' => true],
                ['abc' => 'foo', 1, 'bar', 'baz' => true],
            ],
            [
                ['a' => ['b' => self::createFromString('c'), [self::createFromString('d')]], self::createFromString('e')],
                ['a' => ['b' => 'c', ['d']], 'e'],
            ],
        ];
    }

    /**
     * @dataProvider provideLength
     */
    public function testLength(int $length, string $string)
    {
        if (2 !== grapheme_strlen('च्छे') && 'अनुच्छेद' === $string) {
            self::markTestSkipped('Skipping due to issue ICU-21661.');
        }

        $instance = self::createFromString($string);

        self::assertSame($length, $instance->length());
    }

    public static function provideLength(): array
    {
        return [
            [1, 'a'],
            [2, 'is'],
            [3, 'PHP'],
            [4, 'Java'],
            [7, 'Symfony'],
            [10, 'pineapples'],
            [22, 'Symfony is super cool!'],
        ];
    }

    /**
     * @dataProvider provideIndexOf
     */
    public function testIndexOf(?int $result, string $string, $needle, int $offset)
    {
        $instance = self::createFromString($string);

        self::assertSame($result, $instance->indexOf($needle, $offset));
    }

    public static function provideIndexOf(): array
    {
        return [
            [null, 'abc', '', 0],
            [null, 'ABC', '', 0],
            [null, 'abc', 'd', 0],
            [null, 'abc', 'a', 3],
            [null, 'ABC', 'c', 0],
            [null, 'ABC', 'c', 2],
            [null, 'abc', 'a', -1],
            [null, '123abc', 'B', -3],
            [null, '123abc', 'b', 6],
            [0, 'abc', ['a', 'e'], 0],
            [0, 'abc', 'a', 0],
            [1, 'abc', 'b', 1],
            [2, 'abc', 'c', 1],
            [4, 'abacabab', 'ab', 1],
            [4, '123abc', 'b', -3],
        ];
    }

    /**
     * @dataProvider provideIndexOfIgnoreCase
     */
    public function testIndexOfIgnoreCase(?int $result, string $string, $needle, int $offset)
    {
        $instance = self::createFromString($string);

        self::assertSame($result, $instance->ignoreCase()->indexOf($needle, $offset));
    }

    public static function provideIndexOfIgnoreCase(): array
    {
        return [
            [null, 'ABC', '', 0],
            [null, 'ABC', '', 0],
            [null, 'abc', 'a', 3],
            [null, 'abc', 'A', 3],
            [null, 'abc', 'a', -1],
            [null, 'abc', 'A', -1],
            [null, '123abc', 'B', 6],
            [0, 'ABC', ['a', 'e'], 0],
            [0, 'ABC', 'a', 0],
            [0, 'ABC', 'A', 0],
            [1, 'ABC', 'b', 0],
            [1, 'ABC', 'b', 1],
            [2, 'ABC', 'c', 0],
            [2, 'ABC', 'c', 2],
            [4, 'ABACaBAB', 'Ab', 1],
            [4, '123abc', 'B', -3],
        ];
    }

    /**
     * @dataProvider provideIndexOfLast
     */
    public function testIndexOfLast(?int $result, string $string, $needle, int $offset)
    {
        $instance = self::createFromString($string);

        self::assertSame($result, $instance->indexOfLast($needle, $offset));
    }

    public static function provideIndexOfLast(): array
    {
        return [
            [null, 'abc', '', 0],
            [null, 'abc', '', -2],
            [null, 'elegant', 'z', -1],
            [0, 'abc', ['abc'], 0],
            [5, 'DEJAAAA', 'A', -2],
            [74, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'i', 0],
            [19, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'i', -40],
            [6, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'ipsum', 0],
            [57, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'amet', 0],
            [57, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'amet', -10],
            [22, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'amet', -30],
        ];
    }

    /**
     * @dataProvider provideIndexOfLastIgnoreCase
     */
    public function testIndexOfLastIgnoreCase(?int $result, string $string, string $needle, int $offset)
    {
        $instance = self::createFromString($string);

        self::assertSame($result, $instance->ignoreCase()->indexOfLast($needle, $offset));
    }

    public static function provideIndexOfLastIgnoreCase(): array
    {
        return [
            [null, 'abc', '', 0],
            [null, 'abc', '', -2],
            [null, 'elegant', 'z', -1],
            [1, 'abc', 'b', 0],
            [1, 'abc', 'b', -1],
            [2, 'abcdefgh', 'c', -1],
            [2, 'abcdefgh', 'C', -1],
            [5, 'dejaaaa', 'A', -2],
            [5, 'DEJAAAA', 'a', -2],
            [74, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'I', 0],
            [19, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'I', -40],
            [6, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'IPSUM', 0],
            [57, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'AmeT', 0],
            [57, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'aMEt', -10],
            [22, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'AMET', -30],
        ];
    }

    /**
     * @dataProvider provideSplit
     */
    public function testSplit(string $string, string $delimiter, array $chunks, ?int $limit, int $flags = null)
    {
        self::assertEquals($chunks, self::createFromString($string)->split($delimiter, $limit, $flags));
    }

    public static function provideSplit(): array
    {
        return [
            [
                'hello world',
                ' ',
                [
                    self::createFromString('hello'),
                    self::createFromString('world'),
                ],
                null,
            ],
            [
                'radar',
                'd',
                [
                    self::createFromString('ra'),
                    self::createFromString('ar'),
                ],
                2,
            ],
            [
                'foo,bar,baz,qux,kix',
                ',',
                [
                    self::createFromString('foo'),
                    self::createFromString('bar'),
                    self::createFromString('baz'),
                    self::createFromString('qux'),
                    self::createFromString('kix'),
                ],
                null,
            ],
            [
                'foo,bar,baz,qux,kix',
                ',',
                [
                    self::createFromString('foo,bar,baz,qux,kix'),
                ],
                1,
            ],
            [
                'foo,bar,baz,qux,kix',
                ',',
                [
                    self::createFromString('foo'),
                    self::createFromString('bar'),
                    self::createFromString('baz,qux,kix'),
                ],
                3,
            ],
            [
                'Quisque viverra tincidunt elit. Vestibulum convallis dui nec lacis suscipit cursus.',
                'is',
                [
                    self::createFromString('Qu'),
                    self::createFromString('que viverra tincidunt elit. Vestibulum convall'),
                    self::createFromString(' dui nec lac'),
                    self::createFromString(' suscipit cursus.'),
                ],
                null,
            ],
            [
                'foo,,bar, baz , qux,kix',
                '/\s*,\s*/',
                [
                    self::createFromString('foo'),
                    self::createFromString(''),
                    self::createFromString('bar'),
                    self::createFromString('baz'),
                    self::createFromString('qux,kix'),
                ],
                5,
                AbstractString::PREG_SPLIT,
            ],
            [
                'foo ,,bar, baz',
                '/\s*(,)\s*/',
                [
                    self::createFromString('foo'),
                    self::createFromString(','),
                    self::createFromString(','),
                    self::createFromString('bar'),
                    self::createFromString(','),
                    self::createFromString('baz'),
                ],
                null,
                AbstractString::PREG_SPLIT_NO_EMPTY | AbstractString::PREG_SPLIT_DELIM_CAPTURE,
            ],
            [
                'foo, bar,baz',
                '/\s*(,)\s*/',
                [
                    [self::createFromString('foo'), 0],
                    [self::createFromString('bar'), 5],
                    [self::createFromString('baz'), 9],
                ],
                null,
                AbstractString::PREG_SPLIT_OFFSET_CAPTURE,
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidChunkLength
     */
    public function testInvalidChunkLength(int $length)
    {
        self::expectException(InvalidArgumentException::class);

        self::createFromString('foo|bar|baz')->chunk($length);
    }

    public static function provideInvalidChunkLength(): array
    {
        return [
            [-2],
            [-1],
            [0],
        ];
    }

    /**
     * @dataProvider provideChunk
     */
    public function testChunk(string $string, array $chunks, int $length)
    {
        self::assertEquals($chunks, self::createFromString($string)->chunk($length));
    }

    public static function provideChunk()
    {
        return [
            [
                '',
                [],
                1,
            ],
            [
                'hello',
                [
                    self::createFromString('h'),
                    self::createFromString('e'),
                    self::createFromString('l'),
                    self::createFromString('l'),
                    self::createFromString('o'),
                ],
                1,
            ],
            [
                'hello you!',
                [
                    self::createFromString('h'),
                    self::createFromString('e'),
                    self::createFromString('l'),
                    self::createFromString('l'),
                    self::createFromString('o'),
                    self::createFromString(' '),
                    self::createFromString('y'),
                    self::createFromString('o'),
                    self::createFromString('u'),
                    self::createFromString('!'),
                ],
                1,
            ],
            [
                'hell',
                [
                    self::createFromString('h'),
                    self::createFromString('e'),
                    self::createFromString('l'),
                    self::createFromString('l'),
                ],
                1,
            ],
            [
                'hell',
                [
                    self::createFromString('he'),
                    self::createFromString('ll'),
                ],
                2,
            ],
            [
                str_repeat('-', 65537),
                [
                    self::createFromString(str_repeat('-', 65536)),
                    self::createFromString('-'),
                ],
                65536,
            ],
        ];
    }

    /**
     * @dataProvider provideLower
     */
    public function testLower(string $expected, string $origin)
    {
        $instance = self::createFromString($origin)->lower();

        self::assertNotSame(self::createFromString($origin), $instance);
        self::assertEquals(self::createFromString($expected), $instance);
        self::assertSame($expected, (string) $instance);
    }

    public static function provideLower()
    {
        return [
            ['hello world', 'hello world'],
            ['hello world', 'HELLO WORLD'],
            ['hello world!', 'Hello World!'],
            ['symfony', 'symfony'],
            ['symfony', 'Symfony'],
            ['symfony', 'sYmFOny'],
        ];
    }

    /**
     * @dataProvider provideUpper
     */
    public function testUpper(string $expected, string $origin)
    {
        $instance = self::createFromString($origin)->upper();

        self::assertNotSame(self::createFromString($origin), $instance);
        self::assertEquals(self::createFromString($expected), $instance);
        self::assertSame($expected, (string) $instance);
    }

    public static function provideUpper()
    {
        return [
            ['HELLO WORLD', 'hello world'],
            ['HELLO WORLD', 'HELLO WORLD'],
            ['HELLO WORLD!', 'Hello World!'],
            ['SYMFONY', 'symfony'],
            ['SYMFONY', 'Symfony'],
            ['SYMFONY', 'sYmFOny'],
        ];
    }

    /**
     * @dataProvider provideTitle
     */
    public function testTitle(string $expected, string $origin, bool $allWords)
    {
        self::assertEquals(self::createFromString($expected), self::createFromString($origin)->title($allWords));
    }

    public static function provideTitle()
    {
        return [
            ['Hello world', 'hello world', false],
            ['Hello World', 'hello world', true],
            ['HELLO WORLD', 'HELLO WORLD', false],
            ['HELLO WORLD', 'HELLO WORLD', true],
            ['HELLO wORLD', 'hELLO wORLD', false],
            ['HELLO WORLD', 'hELLO wORLD', true],
            ['Symfony', 'symfony', false],
            ['Symfony', 'Symfony', false],
            ['SYmFOny', 'sYmFOny', false],
        ];
    }

    /**
     * @dataProvider provideSlice
     */
    public function testSlice(string $expected, string $origin, int $start, int $length = null)
    {
        self::assertEquals(self::createFromString($expected), self::createFromString($origin)->slice($start, $length));
    }

    public static function provideSlice()
    {
        return [
            ['Symfony', 'Symfony is awesome', 0, 7],
            [' ', 'Symfony is awesome', 7, 1],
            ['is', 'Symfony is awesome', 8, 2],
            ['is awesome', 'Symfony is awesome', 8, null],
            [' ', 'Symfony is awesome', 10, 1],
            ['awesome', 'Symfony is awesome', 11, 7],
            ['awesome', 'Symfony is awesome', -7, null],
            ['awe', 'Symfony is awesome', -7, -4],
            ['S', 'Symfony is awesome', -42, 1],
            ['', 'Symfony is awesome', 42, 1],
            ['', 'Symfony is awesome', 0, -42],
        ];
    }

    /**
     * @dataProvider provideSplice
     */
    public function testSplice(string $expected, int $start, int $length = null)
    {
        self::assertEquals(self::createFromString($expected), self::createFromString('Symfony is awesome')->splice('X', $start, $length));
    }

    public static function provideSplice()
    {
        return [
            ['X is awesome', 0, 7],
            ['SymfonyXis awesome', 7, 1],
            ['Symfony X awesome', 8, 2],
            ['Symfony X', 8, null],
            ['Symfony isXawesome', 10, 1],
            ['Symfony is X', 11, 7],
            ['Symfony is X', -7, null],
            ['Symfony is Xsome', -7, -4],
            ['Xymfony is awesome', -42, 1],
            ['Symfony is awesomeX', 42, 1],
            ['XSymfony is awesome', 0, -42],
        ];
    }

    /**
     * @dataProvider provideAppend
     */
    public function testAppend(string $expected, array $suffixes)
    {
        $instance = self::createFromString('');
        foreach ($suffixes as $suffix) {
            $instance = $instance->append($suffix);
        }

        self::assertEquals($expected, $instance);

        $instance = self::createFromString('')->append(...$suffixes);

        self::assertEquals(self::createFromString($expected), $instance);
    }

    public static function provideAppend()
    {
        return [
            [
                '',
                [],
            ],
            [
                'Symfony',
                ['Sym', 'fony'],
            ],
            [
                'Hello World!',
                ['Hel', 'lo', ' ', 'World', '!'],
            ],
        ];
    }

    /**
     * @dataProvider provideAppend
     */
    public function testPrepend(string $expected, array $prefixes)
    {
        $instance = self::createFromString('');
        foreach (array_reverse($prefixes) as $suffix) {
            $instance = $instance->prepend($suffix);
        }

        self::assertEquals(self::createFromString($expected), $instance);

        $instance = self::createFromString('')->prepend(...$prefixes);

        self::assertEquals(self::createFromString($expected), $instance);
    }

    /**
     * @dataProvider provideTrim
     */
    public function testTrim(string $expected, string $origin, ?string $chars)
    {
        $result = self::createFromString($origin);
        $result = null !== $chars ? $result->trim($chars) : $result->trim();

        self::assertEquals(self::createFromString($expected), $result);
    }

    public static function provideTrim()
    {
        return [
            [
                "Symfony     IS GREAT\t!!!",
                "    Symfony     IS GREAT\t!!!\n",
                null,
            ],
            [
                "Symfony     IS GREAT\t!!!\n",
                "    Symfony     IS GREAT\t!!!\n",
                ' ',
            ],
            [
                "    Symfony     IS GREAT\t!!!",
                "    Symfony     IS GREAT\t!!!\n",
                "\n",
            ],
            [
                "Symfony     IS GREAT\t",
                "    Symfony     IS GREAT\t!!!\n",
                " \n!",
            ],
            [
                "Symfony     IS GREAT\t!!!  \n",
                "    Symfony     IS GREAT\t!!!  \n",
                ' ',
            ],
        ];
    }

    public function testTrimPrefix()
    {
        $str = self::createFromString('abc.def');

        self::assertEquals(self::createFromString('def'), $str->trimPrefix('abc.'));
        self::assertEquals(self::createFromString('def'), $str->trimPrefix(['abc.', 'def']));
        self::assertEquals(self::createFromString('def'), $str->ignoreCase()->trimPrefix('ABC.'));
    }

    /**
     * @dataProvider provideTrimStart
     */
    public function testTrimStart(string $expected, string $origin, ?string $chars)
    {
        $result = self::createFromString($origin);
        $result = null !== $chars ? $result->trimStart($chars) : $result->trimStart();

        self::assertEquals(self::createFromString($expected), $result);
    }

    public function testTrimSuffix()
    {
        $str = self::createFromString('abc.def');

        self::assertEquals(self::createFromString('abc'), $str->trimSuffix('.def'));
        self::assertEquals(self::createFromString('abc'), $str->trimSuffix(['.def', 'abc']));
        self::assertEquals(self::createFromString('abc'), $str->ignoreCase()->trimSuffix('.DEF'));
    }

    public static function provideTrimStart()
    {
        return [
            [
                "<span>Symfony is a PHP framework</span>\n",
                "\n\t<span>Symfony is a PHP framework</span>\n",
                null,
            ],
            [
                "\t<span>Symfony is a PHP framework</span>\n",
                "\n\t<span>Symfony is a PHP framework</span>\n",
                "\n",
            ],
        ];
    }

    /**
     * @dataProvider provideTrimEnd
     */
    public function testTrimEnd(string $expected, string $origin, ?string $chars)
    {
        $result = self::createFromString($origin);
        $result = null !== $chars ? $result->trimEnd($chars) : $result->trimEnd();

        self::assertEquals(self::createFromString($expected), $result);
    }

    public static function provideTrimEnd()
    {
        return [
            [
                "\n\t<span>Symfony is a PHP framework</span>",
                "\n\t<span>Symfony is a PHP framework</span>  \n",
                null,
            ],
            [
                "\n\t<span>Symfony is a PHP framework</span>  ",
                "\n\t<span>Symfony is a PHP framework</span>  \n",
                "\n",
            ],
            [
                "\n\t<span>Symfony is a PHP framework</span>  \n",
                "\n\t<span>Symfony is a PHP framework</span>  \n",
                ' ',
            ],
        ];
    }

    /**
     * @dataProvider provideBeforeAfter
     */
    public function testBeforeAfter(string $expected, string $needle, string $origin, int $offset, bool $before)
    {
        $result = self::createFromString($origin);
        $result = $before ? $result->before($needle, false, $offset) : $result->after($needle, true, $offset);
        self::assertEquals(self::createFromString($expected), $result);
    }

    public static function provideBeforeAfter()
    {
        return [
            ['hello world', '', 'hello world', 0, true],
            ['hello world', '', 'hello world', 0, false],
            ['hello World', 'w', 'hello World', 0, true],
            ['hello World', 'w', 'hello World', 0, false],
            ['hello world', 'o', 'hello world', 10, true],
            ['hello world', 'o', 'hello world', 10, false],
            ['hello ', 'w', 'hello world', 0, true],
            ['world', 'w', 'hello world', 0, false],
            ['hello W', 'O', 'hello WORLD', 0, true],
            ['ORLD', 'O', 'hello WORLD', 0, false],
            ['abac', 'ab', 'abacabab', 1, true],
            ['abab', 'ab', 'abacabab', 1, false],
        ];
    }

    /**
     * @dataProvider provideBeforeAfterIgnoreCase
     */
    public function testBeforeAfterIgnoreCase(string $expected, string $needle, string $origin, int $offset, bool $before)
    {
        $result = self::createFromString($origin)->ignoreCase();
        $result = $before ? $result->before($needle, false, $offset) : $result->after($needle, true, $offset);
        self::assertEquals(self::createFromString($expected), $result);
    }

    public static function provideBeforeAfterIgnoreCase()
    {
        return [
            ['hello world', '', 'hello world', 0, true],
            ['hello world', '', 'hello world', 0, false],
            ['hello world', 'foo', 'hello world', 0, true],
            ['hello world', 'foo', 'hello world', 0, false],
            ['hello world', 'o', 'hello world', 10, true],
            ['hello world', 'o', 'hello world', 10, false],
            ['hello ', 'w', 'hello world', 0, true],
            ['world', 'w', 'hello world', 0, false],
            ['hello ', 'W', 'hello world', 0, true],
            ['world', 'W', 'hello world', 0, false],
            ['Abac', 'Ab', 'AbacaBAb', 1, true],
            ['aBAb', 'Ab', 'AbacaBAb', 1, false],
        ];
    }

    /**
     * @dataProvider provideBeforeAfterLast
     */
    public function testBeforeAfterLast(string $expected, string $needle, string $origin, int $offset, bool $before)
    {
        $result = self::createFromString($origin);
        $result = $before ? $result->beforeLast($needle, false, $offset) : $result->afterLast($needle, true, $offset);
        self::assertEquals(self::createFromString($expected), $result);
    }

    public static function provideBeforeAfterLast()
    {
        return [
            ['hello world', '', 'hello world', 0, true],
            ['hello world', '', 'hello world', 0, false],
            ['hello world', 'L', 'hello world', 0, true],
            ['hello world', 'L', 'hello world', 0, false],
            ['hello world', 'o', 'hello world', 10, true],
            ['hello world', 'o', 'hello world', 10, false],
            ['hello wor', 'l', 'hello world', 0, true],
            ['ld', 'l', 'hello world', 0, false],
            ['hello w', 'o', 'hello world', 0, true],
            ['orld', 'o', 'hello world', 0, false],
            ['abacab', 'ab', 'abacabab', 1, true],
            ['ab', 'ab', 'abacabab', 1, false],
            ['hello world', 'hello', 'hello world', 0, false],
        ];
    }

    /**
     * @dataProvider provideBeforeAfterLastIgnoreCase
     */
    public function testBeforeAfterLastIgnoreCase(string $expected, string $needle, string $origin, int $offset, bool $before)
    {
        $result = self::createFromString($origin)->ignoreCase();
        $result = $before ? $result->beforeLast($needle, false, $offset) : $result->afterLast($needle, true, $offset);
        self::assertEquals(self::createFromString($expected), $result);
    }

    public static function provideBeforeAfterLastIgnoreCase()
    {
        return [
            ['hello world', '', 'hello world', 0, true],
            ['hello world', '', 'hello world', 0, false],
            ['hello world', 'FOO', 'hello world', 0, true],
            ['hello world', 'FOO', 'hello world', 0, false],
            ['hello world', 'o', 'hello world', 10, true],
            ['hello world', 'o', 'hello world', 10, false],
            ['hello wor', 'l', 'hello world', 0, true],
            ['ld', 'l', 'hello world', 0, false],
            ['hello wor', 'L', 'hello world', 0, true],
            ['ld', 'L', 'hello world', 0, false],
            ['hello w', 'O', 'hello world', 0, true],
            ['orld', 'O', 'hello world', 0, false],
            ['AbacaB', 'Ab', 'AbacaBaB', 1, true],
            ['aB', 'Ab', 'AbacaBaB', 1, false],
        ];
    }

    /**
     * @dataProvider provideFolded
     */
    public function testFolded(string $expected, string $origin)
    {
        self::assertEquals(self::createFromString($expected), self::createFromString($origin)->folded());
    }

    public static function provideFolded()
    {
        return [
            ['hello', 'HELlo'],
            ['world', 'worLd'],
        ];
    }

    /**
     * @dataProvider provideReplace
     */
    public function testReplace(string $expectedString, int $expectedCount, string $origin, string $from, string $to)
    {
        $origin = self::createFromString($origin);
        $result = $origin->replace($from, $to);

        self::assertEquals(self::createFromString($expectedString), $result);
    }

    public static function provideReplace()
    {
        return [
            ['hello world', 0, 'hello world', '', ''],
            ['hello world', 0, 'hello world', '', '_'],
            ['helloworld',  1, 'hello world', ' ', ''],
            ['hello_world', 1, 'hello world', ' ', '_'],
            ['hemmo wormd', 3, 'hello world', 'l', 'm'],
            ['hello world', 0, 'hello world', 'L', 'm'],
        ];
    }

    /**
     * @dataProvider provideReplaceMatches
     */
    public function testReplaceMatches(string $expectedString, string $origin, string $fromRegexp, $to)
    {
        $origin = self::createFromString($origin);
        $result = $origin->replaceMatches($fromRegexp, $to);

        self::assertEquals(self::createFromString($expectedString), $result);
    }

    public static function provideReplaceMatches()
    {
        return [
            ['April,15,2003', 'April 15, 2003', '/(\w+) (\d+), (\d+)/i', '${1},$2,$3'],
            ['5/27/1999', '1999-5-27', '/(19|20)(\d{2})-(\d{1,2})-(\d{1,2})/', '\3/\4/\1\2'],
            ['Copyright 2000', 'Copyright 1999', '([0-9]+)', '2000'],
            ['hello world! this is a test', 'HELLO WORLD! THIS is a test', '/\b([A-Z]+)\b/', function ($word) {
                return strtolower($word[1]);
            }],
            ['COPYRIGHT 1999', 'Copyright 1999', '/[a-z]/', function ($matches) {
                foreach ($matches as $match) {
                    return strtoupper($match);
                }
            }],
        ];
    }

    /**
     * @dataProvider provideReplaceIgnoreCase
     */
    public function testReplaceIgnoreCase(string $expectedString, int $expectedCount, string $origin, string $from, string $to)
    {
        $origin = self::createFromString($origin);
        $result = $origin->ignoreCase()->replace($from, $to);

        self::assertEquals(self::createFromString($expectedString), $result);
    }

    public static function provideReplaceIgnoreCase()
    {
        return [
            ['hello world', 0, 'hello world', '', ''],
            ['hello world', 0, 'hello world', '', '_'],
            ['helloworld',  1, 'hello world', ' ', ''],
            ['hello_world', 1, 'hello world', ' ', '_'],
            ['hemmo wormd', 3, 'hello world', 'l', 'm'],
            ['heMMo worMd', 3, 'hello world', 'L', 'M'],
        ];
    }

    /**
     * @dataProvider provideCamel
     */
    public function testCamel(string $expectedString, string $origin)
    {
        $instance = self::createFromString($origin)->camel();

        self::assertEquals(self::createFromString($expectedString), $instance);
    }

    public static function provideCamel()
    {
        return [
            ['', ''],
            ['xY', 'x_y'],
            ['xuYo', 'xu_yo'],
            ['symfonyIsGreat', 'symfony_is_great'],
            ['symfony5IsGreat', 'symfony_5_is_great'],
            ['symfonyIsGreat', 'Symfony is great'],
            ['symfonyIsAGreatFramework', 'Symfony is a great framework'],
            ['symfonyIsGREAT', '*Symfony* is GREAT!!'],
            ['SYMFONY', 'SYMFONY'],
        ];
    }

    /**
     * @dataProvider provideSnake
     */
    public function testSnake(string $expectedString, string $origin)
    {
        $instance = self::createFromString($origin)->snake();

        self::assertEquals(self::createFromString($expectedString), $instance);
    }

    public static function provideSnake()
    {
        return [
            ['', ''],
            ['x_y', 'x_y'],
            ['x_y', 'X_Y'],
            ['xu_yo', 'xu_yo'],
            ['symfony_is_great', 'symfonyIsGreat'],
            ['symfony5_is_great', 'symfony5IsGreat'],
            ['symfony5is_great', 'symfony5isGreat'],
            ['symfony_is_great', 'Symfony is great'],
            ['symfony_is_a_great_framework', 'symfonyIsAGreatFramework'],
            ['symfony_is_great', 'symfonyIsGREAT'],
            ['symfony_is_really_great', 'symfonyIsREALLYGreat'],
            ['symfony', 'SYMFONY'],
        ];
    }

    /**
     * @dataProvider provideStartsWith
     */
    public function testStartsWith(bool $expected, string $origin, $prefix, int $form = null)
    {
        $instance = self::createFromString($origin);
        $instance = $form ? $instance->normalize($form) : $instance;

        self::assertSame($expected, $instance->startsWith($prefix));
    }

    public static function provideStartsWith()
    {
        return [
            [false, '', ''],
            [false, '', 'foo'],
            [false, 'foo', ''],
            [false, 'foo', 'o'],
            [false, 'foo', 'F'],
            [false, "\nfoo", 'f'],
            [true, 'foo', 'f'],
            [true, 'foo', 'fo'],
            [true, 'foo', new ByteString('f')],
            [true, 'foo', new CodePointString('f')],
            [true, 'foo', new UnicodeString('f')],
            [true, 'foo', ['e', 'f', 'g']],
        ];
    }

    /**
     * @dataProvider provideStartsWithIgnoreCase
     */
    public function testStartsWithIgnoreCase(bool $expected, string $origin, $prefix)
    {
        self::assertSame($expected, self::createFromString($origin)->ignoreCase()->startsWith($prefix));
    }

    public static function provideStartsWithIgnoreCase()
    {
        return [
            [false, '', ''],
            [false, '', 'foo'],
            [false, 'foo', ''],
            [false, 'foo', 'o'],
            [false, "\nfoo", 'f'],
            [true, 'foo', 'F'],
            [true, 'FoO', 'foo'],
            [true, 'foo', new ByteString('F')],
            [true, 'foo', new CodePointString('F')],
            [true, 'foo', new UnicodeString('F')],
            [true, 'foo', ['E', 'F', 'G']],
        ];
    }

    /**
     * @dataProvider provideEndsWith
     */
    public function testEndsWith(bool $expected, string $origin, $suffix, int $form = null)
    {
        $instance = self::createFromString($origin);
        $instance = $form ? $instance->normalize($form) : $instance;

        self::assertSame($expected, $instance->endsWith($suffix));
    }

    public static function provideEndsWith()
    {
        return [
            [false, '', ''],
            [false, '', 'foo'],
            [false, 'foo', ''],
            [false, 'foo', 'f'],
            [false, 'foo', 'O'],
            [false, "foo\n", 'o'],
            [true, 'foo', 'o'],
            [true, 'foo', 'foo'],
            [true, 'foo', new ByteString('o')],
            [true, 'foo', new CodePointString('o')],
            [true, 'foo', new UnicodeString('o')],
            [true, 'foo', ['a', 'o', 'u']],
        ];
    }

    /**
     * @dataProvider provideEndsWithIgnoreCase
     */
    public function testEndsWithIgnoreCase(bool $expected, string $origin, $suffix)
    {
        self::assertSame($expected, self::createFromString($origin)->ignoreCase()->endsWith($suffix));
    }

    public static function provideEndsWithIgnoreCase()
    {
        return [
            [false, '', ''],
            [false, '', 'foo'],
            [false, 'foo', ''],
            [false, 'foo', 'f'],
            [false, "foo\n", 'o'],
            [true, 'foo', 'O'],
            [true, 'Foo', 'foo'],
            [true, 'foo', new ByteString('O')],
            [true, 'foo', new CodePointString('O')],
            [true, 'foo', new UnicodeString('O')],
            [true, 'foo', ['A', 'O', 'U']],
        ];
    }

    /**
     * @dataProvider provideEnsureStart
     */
    public function testEnsureStart(string $expectedString, string $origin, $prefix)
    {
        $instance = self::createFromString($origin)->ensureStart($prefix);

        self::assertEquals(self::createFromString($expectedString), $instance);
    }

    public static function provideEnsureStart()
    {
        return [
            ['', '', ''],
            ['foo', 'foo', ''],
            ['foo', '', 'foo'],
            ['foo', 'foo', 'foo'],
            ['foobar', 'foobar', 'foo'],
            ['foobar', 'bar', 'foo'],
            ['foo', 'foofoofoo', 'foo'],
            ['foobar', 'foofoofoobar', 'foo'],
            ['fooFoobar', 'Foobar', 'foo'],
            ["foo\nfoo", "\nfoo", 'foo'],
        ];
    }

    /**
     * @dataProvider provideEnsureStartIgnoreCase
     */
    public function testEnsureStartIgnoreCase(string $expectedString, string $origin, $prefix)
    {
        $instance = self::createFromString($origin)->ignoreCase()->ensureStart($prefix);

        self::assertEquals(self::createFromString($expectedString), $instance);
    }

    public static function provideEnsureStartIgnoreCase()
    {
        return [
            ['', '', ''],
            ['foo', 'foo', ''],
            ['foo', '', 'foo'],
            ['Foo', 'Foo', 'foo'],
            ['Foobar', 'Foobar', 'foo'],
            ['foobar', 'bar', 'foo'],
            ['Foo', 'fOofoOFoo', 'foo'],
            ['Foobar', 'fOofoOFoobar', 'foo'],
            ["foo\nfoo", "\nfoo", 'foo'],
        ];
    }

    /**
     * @dataProvider provideEnsureEnd
     */
    public function testEnsureEnd(string $expectedString, string $origin, $suffix)
    {
        $instance = self::createFromString($origin)->ensureEnd($suffix);

        self::assertEquals(self::createFromString($expectedString), $instance);
    }

    public static function provideEnsureEnd()
    {
        return [
            ['', '', ''],
            ['foo', 'foo', ''],
            ['foo', '', 'foo'],
            ['foo', 'foo', 'foo'],
            ['foobar', 'foobar', 'bar'],
            ['foobar', 'foo', 'bar'],
            ['foo', 'foofoofoo', 'foo'],
            ['foobar', 'foobarbarbar', 'bar'],
            ['fooBarbar', 'fooBar', 'bar'],
            ["foo\nfoo", "foo\n", 'foo'],
        ];
    }

    /**
     * @dataProvider provideEnsureEndIgnoreCase
     */
    public function testEnsureEndIgnoreCase(string $expectedString, string $origin, $suffix)
    {
        $instance = self::createFromString($origin)->ignoreCase()->ensureEnd($suffix);

        self::assertEquals(self::createFromString($expectedString), $instance);
    }

    public static function provideEnsureEndIgnoreCase()
    {
        return [
            ['', '', ''],
            ['foo', 'foo', ''],
            ['foo', '', 'foo'],
            ['foo', 'foo', 'foo'],
            ['fooBar', 'fooBar', 'bar'],
            ['foobar', 'foo', 'bar'],
            ['fOo', 'fOofoOFoo', 'foo'],
            ['fooBar', 'fooBarbArbaR', 'bar'],
            ["foo\nfoo", "foo\n", 'foo'],
        ];
    }

    /**
     * @dataProvider provideCollapseWhitespace
     */
    public function testCollapseWhitespace(string $expectedString, string $origin)
    {
        $instance = self::createFromString($origin)->collapseWhitespace();

        self::assertEquals(self::createFromString($expectedString), $instance);
    }

    public static function provideCollapseWhitespace()
    {
        return [
            ['', ''],
            ['', " \t\r\n"],
            ['foo bar', 'foo bar'],
            ['foo bar baz', ' foo   bar baz'],
            ['foo bar baz', " foo\nbar \t baz\n"],
        ];
    }

    /**
     * @dataProvider provideEqualsTo
     */
    public function testEqualsTo(bool $expected, string $origin, $other)
    {
        self::assertSame($expected, self::createFromString($origin)->equalsTo($other));
    }

    public static function provideEqualsTo()
    {
        return [
            [true, '', ''],
            [false, '', 'foo'],
            [false, 'foo', ''],
            [false, 'foo', 'Foo'],
            [false, "foo\n", 'foo'],
            [true, 'Foo bar', 'Foo bar'],
            [true, 'Foo bar', new ByteString('Foo bar')],
            [true, 'Foo bar', new CodePointString('Foo bar')],
            [true, 'Foo bar', new UnicodeString('Foo bar')],
            [false, '', []],
            [false, 'foo', ['bar', 'baz']],
            [true, 'foo', ['bar', 'foo', 'baz']],
        ];
    }

    /**
     * @dataProvider provideEqualsToIgnoreCase
     */
    public function testEqualsToIgnoreCase(bool $expected, string $origin, $other)
    {
        self::assertSame($expected, self::createFromString($origin)->ignoreCase()->equalsTo($other));
    }

    public static function provideEqualsToIgnoreCase()
    {
        return [
            [true, '', ''],
            [false, '', 'foo'],
            [false, 'foo', ''],
            [false, "foo\n", 'foo'],
            [true, 'foo Bar', 'FOO bar'],
            [true, 'foo Bar', new ByteString('FOO bar')],
            [true, 'foo Bar', new CodePointString('FOO bar')],
            [true, 'foo Bar', new UnicodeString('FOO bar')],
            [false, '', []],
            [false, 'Foo', ['bar', 'baz']],
            [true, 'Foo', ['bar', 'foo', 'baz']],
        ];
    }

    /**
     * @dataProvider provideIsEmpty
     */
    public function testIsEmpty(bool $expected, string $origin)
    {
        self::assertSame($expected, self::createFromString($origin)->isEmpty());
    }

    public static function provideIsEmpty()
    {
        return [
            [true, ''],
            [false, ' '],
            [false, "\n"],
            [false, 'Foo bar'],
        ];
    }

    /**
     * @dataProvider provideJoin
     */
    public function testJoin(string $expected, string $origin, array $join)
    {
        $instance = self::createFromString($origin)->join($join);

        self::assertEquals(self::createFromString($expected), $instance);
    }

    public function testJoinWithLastGlue()
    {
        self::assertSame('foo, bar and baz', (string) self::createFromString(', ')->join(['foo', 'bar', 'baz'], ' and '));
    }

    public static function provideJoin()
    {
        return [
            ['', '', []],
            ['', ',', []],
            ['foo', ',', ['foo']],
            ['foobar', '', ['foo', 'bar']],
            ['foo, bar', ', ', ['foo', 'bar']],
        ];
    }

    /**
     * @dataProvider provideRepeat
     */
    public function testRepeat(string $expected, string $origin, int $multiplier)
    {
        $instance = self::createFromString($origin)->repeat($multiplier);

        self::assertEquals(self::createFromString($expected), $instance);
    }

    public static function provideRepeat()
    {
        return [
            ['', '', 0],
            ['', '', 5],
            ['', 'foo', 0],
            ['foo', 'foo', 1],
            ['foofoofoo', 'foo', 3],
        ];
    }

    /**
     * @dataProvider providePadBoth
     */
    public function testPadBoth(string $expected, string $origin, int $length, string $padStr)
    {
        $instance = self::createFromString($origin)->padBoth($length, $padStr);

        self::assertEquals(self::createFromString($expected), $instance);
    }

    public static function providePadBoth()
    {
        return [
            ['', '', 0, '_'],
            ['###', '', 3, '#'],
            ['foo', 'foo', 2, '#'],
            ['foo', 'foo', 3, '#'],
            ['foo#', 'foo', 4, '#'],
            ['#foo#', 'foo', 5, '#'],
            ['##foo###', 'foo', 8, '#'],
            ['#+#foo#+#', 'foo', 9, '#+'],
        ];
    }

    /**
     * @dataProvider providePadEnd
     */
    public function testPadEnd(string $expected, string $origin, int $length, string $padStr)
    {
        $instance = self::createFromString($origin)->padEnd($length, $padStr);

        self::assertEquals(self::createFromString($expected), $instance);
    }

    public static function providePadEnd()
    {
        return [
            ['', '', 0, '_'],
            ['###', '', 3, '#'],
            ['foo', 'foo', 2, '#'],
            ['foo', 'foo', 3, '#'],
            ['foo#', 'foo', 4, '#'],
            ['foo###', 'foo', 6, '#'],
            ['foo#+#', 'foo', 6, '#+'],
        ];
    }

    /**
     * @dataProvider providePadStart
     */
    public function testPadStart(string $expected, string $origin, int $length, string $padStr)
    {
        $instance = self::createFromString($origin)->padStart($length, $padStr);

        self::assertEquals(self::createFromString($expected), $instance);
    }

    public static function providePadStart()
    {
        return [
            ['', '', 0, '_'],
            ['###', '', 3, '#'],
            ['foo', 'foo', 2, '#'],
            ['foo', 'foo', 3, '#'],
            ['#foo', 'foo', 4, '#'],
            ['###foo', 'foo', 6, '#'],
            ['#+#foo', 'foo', 6, '#+'],
        ];
    }

    /**
     * @dataProvider provideTruncate
     */
    public function testTruncate(string $expected, string $origin, int $length, string $ellipsis, bool $cut = true)
    {
        $instance = self::createFromString($origin)->truncate($length, $ellipsis, $cut);

        self::assertEquals(self::createFromString($expected), $instance);
    }

    public static function provideTruncate()
    {
        return [
            ['', '', 3, ''],
            ['', 'foo', 0, '...'],
            ['foo', 'foo', 0, '...', false],
            ['fo', 'foobar', 2, ''],
            ['foobar', 'foobar', 10, ''],
            ['foobar', 'foobar', 10, '...', false],
            ['foo', 'foo', 3, '...'],
            ['fo', 'foobar', 2, '...'],
            ['...', 'foobar', 3, '...'],
            ['fo...', 'foobar', 5, '...'],
            ['foobar...', 'foobar foo', 6, '...', false],
            ['foobar...', 'foobar foo', 7, '...', false],
            ['foobar foo...', 'foobar foo a', 10, '...', false],
            ['foobar foo aar', 'foobar foo aar', 12, '...', false],
        ];
    }

    public function testToString()
    {
        $instance = self::createFromString('foobar');

        self::assertSame('foobar', $instance->toString());
    }

    /**
     * @dataProvider provideReverse
     */
    public function testReverse(string $expected, string $origin)
    {
        $instance = self::createFromString($origin)->reverse();

        self::assertEquals(self::createFromString($expected), $instance);
    }

    public static function provideReverse()
    {
        return [
            ['', ''],
            ['oof', 'foo'],
            ["\n!!!\tTAERG SI     ynofmyS    ", "    Symfony     IS GREAT\t!!!\n"],
        ];
    }

    /**
     * @dataProvider provideWidth
     */
    public function testWidth(int $expected, string $origin, bool $ignoreAnsiDecoration = true)
    {
        self::assertSame($expected, self::createFromString($origin)->width($ignoreAnsiDecoration));
    }

    public static function provideWidth(): array
    {
        return [
            [0, ''],
            [1, 'c'],
            [3, 'foo'],
            [2, '⭐'],
            [8, 'f⭐o⭐⭐'],
            [19, 'コンニチハ, セカイ!'],
            [6, "foo\u{0000}bar"],
            [6, "foo\u{001b}[0mbar"],
            [6, "foo\u{0001}bar"],
            [6, "foo\u{0001}bar", false],
            [4, '--ֿ--'],
            [4, 'café'],
            [1, 'А҈'],
            [4, 'ᬓᬨᬮ᭄'],
            [1, "\u{00AD}"],
            [14, "\u{007f}\u{007f}f\u{001b}[0moo\u{0001}bar\u{007f}cccïf\u{008e}cy\u{0005}1"], // foobarcccïfcy1
            [17, "\u{007f}\u{007f}f\u{001b}[0moo\u{0001}bar\u{007f}cccïf\u{008e}cy\u{0005}1", false], // f[0moobarcccïfcy1
        ];
    }
}
