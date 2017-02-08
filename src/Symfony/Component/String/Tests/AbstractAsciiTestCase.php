<?php

namespace Symfony\Component\String\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\String\AbstractString;
use Symfony\Component\String\Exception\InvalidArgumentException;

abstract class AbstractAsciiTestCase extends TestCase
{
    protected static function createFromString(string $string): AbstractString
    {
        throw new \BadMethodCallException('This method must be implemented by subclasses.');
    }

    public function testCreateFromString()
    {
        $bytes = static::createFromString('Symfony is a PHP framework!');

        $this->assertSame('Symfony is a PHP framework!', (string) $bytes);
        $this->assertSame(27, $bytes->length());
        $this->assertFalse($bytes->isEmpty());
    }

    public function testCreateFromEmptyString()
    {
        $instance = static::createFromString('');

        $this->assertSame('', (string) $instance);
        $this->assertSame(0, $instance->length());
        $this->assertTrue($instance->isEmpty());
    }

    /**
     * @dataProvider provideLength
     */
    public function testLength(int $length, string $string)
    {
        $instance = static::createFromString($string);

        $this->assertSame($length, $instance->length());
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
    public function testIndexOf(?int $result, string $string, string $needle, int $offset)
    {
        $instance = static::createFromString($string);

        $this->assertSame($result, $instance->indexOf($needle, $offset));
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
            [0, 'abc', 'a', 0],
            [1, 'abc', 'b', 1],
            [2, 'abc', 'c', 1],
            [4, '123abc', 'b', -3],
        ];
    }

    /**
     * @dataProvider provideIndexOfIgnoreCase
     */
    public function testIndexOfIgnoreCase(?int $result, string $string, string $needle, int $offset)
    {
        $instance = static::createFromString($string);

        $this->assertSame($result, $instance->ignoreCase()->indexOf($needle, $offset));
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
            [0, 'ABC', 'a', 0],
            [0, 'ABC', 'A', 0],
            [1, 'ABC', 'b', 0],
            [1, 'ABC', 'b', 1],
            [2, 'ABC', 'c', 0],
            [2, 'ABC', 'c', 2],
            [4, '123abc', 'B', -3],
        ];
    }

    /**
     * @dataProvider provideIndexOfLast
     */
    public function testIndexOfLast(?int $result, string $string, string $needle, int $offset)
    {
        $instance = static::createFromString($string);

        $this->assertSame($result, $instance->indexOfLast($needle, $offset));
    }

    public static function provideIndexOfLast(): array
    {
        return [
            [null, 'abc', '', 0],
            [null, 'abc', '', -2],
            [null, 'elegant', 'z', -1],
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
        $instance = static::createFromString($string);

        $this->assertSame($result, $instance->ignoreCase()->indexOfLast($needle, $offset));
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
    public function testSplit(string $string, string $delimiter, array $chunks, ?int $limit)
    {
        $this->assertEquals($chunks, static::createFromString($string)->split($delimiter, $limit));
    }

    public static function provideSplit(): array
    {
        return [
            [
                'hello world',
                ' ',
                [
                    static::createFromString('hello'),
                    static::createFromString('world'),
                ],
                null,
            ],
            [
                'radar',
                'd',
                [
                    static::createFromString('ra'),
                    static::createFromString('ar'),
                ],
                2,
            ],
            [
                'foo,bar,baz,qux,kix',
                ',',
                [
                    static::createFromString('foo'),
                    static::createFromString('bar'),
                    static::createFromString('baz'),
                    static::createFromString('qux'),
                    static::createFromString('kix'),
                ],
                null,
            ],
            [
                'foo,bar,baz,qux,kix',
                ',',
                [
                    static::createFromString('foo,bar,baz,qux,kix'),
                ],
                1,
            ],
            [
                'foo,bar,baz,qux,kix',
                ',',
                [
                    static::createFromString('foo'),
                    static::createFromString('bar'),
                    static::createFromString('baz,qux,kix'),
                ],
                3,
            ],
            [
                'Quisque viverra tincidunt elit. Vestibulum convallis dui nec lacis suscipit cursus.',
                'is',
                [
                    static::createFromString('Qu'),
                    static::createFromString('que viverra tincidunt elit. Vestibulum convall'),
                    static::createFromString(' dui nec lac'),
                    static::createFromString(' suscipit cursus.'),
                ],
                null,
            ],
        ];
    }

    /**
     * @dataProvider provideInvalidChunkLength
     */
    public function testInvalidChunkLength(int $length)
    {
        $this->expectException(InvalidArgumentException::class);

        static::createFromString('foo|bar|baz')->chunk($length);
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
        $this->assertEquals($chunks, static::createFromString($string)->chunk($length));
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
                    static::createFromString('h'),
                    static::createFromString('e'),
                    static::createFromString('l'),
                    static::createFromString('l'),
                    static::createFromString('o'),
                ],
                1,
            ],
            [
                'hello you!',
                [
                    static::createFromString('h'),
                    static::createFromString('e'),
                    static::createFromString('l'),
                    static::createFromString('l'),
                    static::createFromString('o'),
                    static::createFromString(' '),
                    static::createFromString('y'),
                    static::createFromString('o'),
                    static::createFromString('u'),
                    static::createFromString('!'),
                ],
                1,
            ],
            [
                'hell',
                [
                    static::createFromString('h'),
                    static::createFromString('e'),
                    static::createFromString('l'),
                    static::createFromString('l'),
                ],
                1,
            ],
            [
                'hell',
                [
                    static::createFromString('he'),
                    static::createFromString('ll'),
                ],
                2,
            ],
            [
                str_repeat('-', 65537),
                [
                    static::createFromString(str_repeat('-', 65536)),
                    static::createFromString('-'),
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
        $instance = static::createFromString($origin)->lower();

        $this->assertNotSame(static::createFromString($origin), $instance);
        $this->assertEquals(static::createFromString($expected), $instance);
        $this->assertSame($expected, (string) $instance);
    }

    public static function provideLower()
    {
        return [
            ['hello world', 'hello world'],
            ['hello world', 'HELLO WORLD'],
            ['hello world', 'Hello World'],
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
        $instance = static::createFromString($origin)->upper();

        $this->assertNotSame(static::createFromString($origin), $instance);
        $this->assertEquals(static::createFromString($expected), $instance);
        $this->assertSame($expected, (string) $instance);
    }

    public static function provideUpper()
    {
        return [
            ['HELLO WORLD', 'hello world'],
            ['HELLO WORLD', 'HELLO WORLD'],
            ['HELLO WORLD', 'Hello World'],
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
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->title($allWords)
        );
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
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->slice($start, $length)
        );
    }

    public static function provideSlice()
    {
        return [
            ['Symfony', 'Symfony is awesome', 0, 7],
            [' ', 'Symfony is awesome', 7, 1],
            ['is', 'Symfony is awesome', 8, 2],
            [' ', 'Symfony is awesome', 10, 1],
            ['awesome', 'Symfony is awesome', 11, 7],
        ];
    }

    /**
     * @dataProvider provideAppend
     */
    public function testAppend(string $expected, array $suffixes)
    {
        $instance = static::createFromString('');
        foreach ($suffixes as $suffix) {
            $instance = $instance->append($suffix);
        }

        $this->assertEquals($expected, $instance);

        $instance = static::createFromString('')->append(...$suffixes);

        $this->assertEquals(static::createFromString($expected), $instance);
    }

    public static function provideAppend()
    {
        return [
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
        $instance = static::createFromString('');
        foreach (array_reverse($prefixes) as $suffix) {
            $instance = $instance->prepend($suffix);
        }

        $this->assertEquals(static::createFromString($expected), $instance);

        $instance = static::createFromString('')->prepend(...$prefixes);

        $this->assertEquals(static::createFromString($expected), $instance);
    }

    /**
     * @dataProvider provideTrim
     */
    public function testTrim(string $expected, string $origin, ?string $chars)
    {
        $result = static::createFromString($origin);
        $result = null !== $chars ? $result->trim($chars) : $result->trim();

        $this->assertEquals(static::createFromString($expected), $result);
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
        ];
    }

    /**
     * @dataProvider provideTrimStart
     */
    public function testTrimStart(string $expected, string $origin, ?string $chars)
    {
        $result = static::createFromString($origin);
        $result = null !== $chars ? $result->trimStart($chars) : $result->trimStart();

        $this->assertEquals(static::createFromString($expected), $result);
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
        $result = static::createFromString($origin);
        $result = null !== $chars ? $result->trimEnd($chars) : $result->trimEnd();

        $this->assertEquals(static::createFromString($expected), $result);
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
        ];
    }

    /**
     * @dataProvider provideBeforeAfter
     */
    public function testBeforeAfter(string $expected, string $needle, string $origin, bool $before)
    {
        $result = static::createFromString($origin);
        $result = $before ? $result->before($needle, false) : $result->after($needle, true);
        $this->assertEquals(static::createFromString($expected), $result);
    }

    public static function provideBeforeAfter()
    {
        return [
            ['', '', 'hello world', true],
            ['', '', 'hello world', false],
            ['', 'w', 'hello World', true],
            ['', 'w', 'hello World', false],
            ['hello ', 'w', 'hello world', true],
            ['world', 'w', 'hello world', false],
        ];
    }

    /**
     * @dataProvider provideBeforeAfterIgnoreCase
     */
    public function testBeforeAfterIgnoreCase(string $expected, string $needle, string $origin, bool $before)
    {
        $result = static::createFromString($origin)->ignoreCase();
        $result = $before ? $result->before($needle, false) : $result->after($needle, true);
        $this->assertEquals(static::createFromString($expected), $result);
    }

    public static function provideBeforeAfterIgnoreCase()
    {
        return [
            ['', '', 'hello world', true],
            ['', '', 'hello world', false],
            ['', 'foo', 'hello world', true],
            ['', 'foo', 'hello world', false],
            ['hello ', 'w', 'hello world', true],
            ['world', 'w', 'hello world', false],
            ['hello ', 'W', 'hello world', true],
            ['world', 'W', 'hello world', false],
        ];
    }

    /**
     * @dataProvider provideBeforeAfterLast
     */
    public function testBeforeAfterLast(string $expected, string $needle, string $origin, bool $before)
    {
        $result = static::createFromString($origin);
        $result = $before ? $result->beforeLast($needle, false) : $result->afterLast($needle, true);
        $this->assertEquals(static::createFromString($expected), $result);
    }

    public static function provideBeforeAfterLast()
    {
        return [
            ['', '', 'hello world', true],
            ['', '', 'hello world', false],
            ['', 'L', 'hello world', true],
            ['', 'L', 'hello world', false],
            ['hello wor', 'l', 'hello world', true],
            ['ld', 'l', 'hello world', false],
            ['hello w', 'o', 'hello world', true],
            ['orld', 'o', 'hello world', false],
        ];
    }

    /**
     * @dataProvider provideBeforeAfterLastIgnoreCase
     */
    public function testBeforeAfterLastIgnoreCase(string $expected, string $needle, string $origin, bool $before)
    {
        $result = static::createFromString($origin)->ignoreCase();
        $result = $before ? $result->beforeLast($needle, false) : $result->afterLast($needle, true);
        $this->assertEquals(static::createFromString($expected), $result);
    }

    public static function provideBeforeAfterLastIgnoreCase()
    {
        return [
            ['', '', 'hello world', true],
            ['', '', 'hello world', false],
            ['', 'FOO', 'hello world', true],
            ['', 'FOO', 'hello world', false],
            ['hello wor', 'l', 'hello world', true],
            ['ld', 'l', 'hello world', false],
            ['hello wor', 'L', 'hello world', true],
            ['ld', 'L', 'hello world', false],
            ['hello w', 'O', 'hello world', true],
            ['orld', 'O', 'hello world', false],
        ];
    }

    /**
     * @dataProvider provideFolded
     */
    public function testFolded(string $expected, string $origin)
    {
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->folded()
        );
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
        $origin = static::createFromString($origin);
        $result = $origin->replace($from, $to);

        $this->assertEquals(static::createFromString($expectedString), $result);
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
     * @dataProvider provideReplaceIgnoreCase
     */
    public function testReplaceIgnoreCase(string $expectedString, int $expectedCount, string $origin, string $from, string $to)
    {
        $origin = static::createFromString($origin);
        $result = $origin->ignoreCase()->replace($from, $to);

        $this->assertEquals(static::createFromString($expectedString), $result);
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
}
