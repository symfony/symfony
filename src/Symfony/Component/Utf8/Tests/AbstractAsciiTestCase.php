<?php

namespace Symfony\Component\Utf8\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Utf8\GenericStringInterface;

/**
 * @requires PHP 7
 */
abstract class AbstractAsciiTestCase extends TestCase
{
    protected static function createFromString(string $string)
    {
        throw new \BadMethodCallException('This method must be implemented by subclasses.');
    }

    public function testCreateFromString()
    {
        $bytes = static::createFromString('Symfony is a PHP framework!');

        $this->assertInstanceOf(GenericStringInterface::class, $bytes);
        $this->assertInstanceOf(\Countable::class, $bytes);
        $this->assertSame('Symfony is a PHP framework!', (string) $bytes);
        $this->assertSame(27, $bytes->length());
        $this->assertFalse($bytes->isEmpty());
    }

    public function testCreateFromEmptyString()
    {
        $instance = static::createFromString('');

        $this->assertInstanceOf(GenericStringInterface::class, $instance);
        $this->assertInstanceOf(\Countable::class, $instance);
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
        $this->assertSame($length, count($instance));
    }

    public static function provideLength()
    {
        return array(
            array(1, 'a'),
            array(2, 'is'),
            array(3, 'PHP'),
            array(4, 'Java'),
            array(7, 'Symfony'),
            array(10, 'pineapples'),
            array(22, 'Symfony is super cool!'),
        );
    }

    /**
     * @dataProvider provideIndexOfData
     */
    public function testIndexOf($result, string $string, string $needle, int $offset)
    {
        $instance = static::createFromString($string);

        $this->assertSame($result, $instance->indexOf($needle, $offset));
    }

    public static function provideIndexOfData()
    {
        return array(
            array(null, 'abc', '', 0),
            array(null, 'abc', 'd', 0),
            array(null, 'abc', 'a', 3),
            array(null, 'ABC', '', 0),
            array(null, 'ABC', 'c', 0),
            array(null, 'ABC', 'c', 2),
            array(null, 'abc', 'a', -1),
            array(null, '123abc', 'B', -3),
            array(null, '123abc', 'b', 6),
            array(0, 'abc', 'a', 0),
            array(1, 'abc', 'b', 1),
            array(2, 'abc', 'c', 1),
            array(4, '123abc', 'b', -3),
        );
    }

    /**
     * @dataProvider provideIndexOfIgnoreCaseData
     */
    public function testIndexOfIgnoreCase($result, string $string, string $needle, int $offset)
    {
        $instance = static::createFromString($string);

        $this->assertSame($result, $instance->indexOfIgnoreCase($needle, $offset));
    }

    public static function provideIndexOfIgnoreCaseData()
    {
        return array(
            array(null, 'ABC', '', 0),
            array(null, 'abc', 'a', 3),
            array(null, 'abc', 'A', 3),
            array(null, 'ABC', '', 0),
            array(null, 'abc', 'a', -1),
            array(null, 'abc', 'A', -1),
            array(null, '123abc', 'B', 6),
            array(0, 'ABC', 'a', 0),
            array(0, 'ABC', 'A', 0),
            array(1, 'ABC', 'b', 0),
            array(1, 'ABC', 'b', 1),
            array(2, 'ABC', 'c', 0),
            array(2, 'ABC', 'c', 2),
            array(4, '123abc', 'B', -3),
        );
    }

    /**
     * @dataProvider provideLastIndexOfData
     */
    public function testLastIndexOf($result, string $string, string $needle, int $offset)
    {
        $instance = static::createFromString($string);

        $this->assertSame($result, $instance->lastIndexOf($needle, $offset));
    }

    public static function provideLastIndexOfData()
    {
        return array(
            array(null, 'abc', '', 0),
            array(null, 'abc', '', -2),
            array(null, 'elegant', 'z', -1),
            array(5, 'DEJAAAA', 'A', -2),
            array(74, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'i', 0),
            array(19, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'i', -40),
            array(6, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'ipsum', 0),
            array(57, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'amet', 0),
            array(57, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'amet', -10),
            array(22, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'amet', -30),
        );
    }

    /**
     * @dataProvider provideLastIndexOfIgnoreCaseData
     */
    public function testLastIndexOfIgnoreCase($result, string $string, string $needle, int $offset)
    {
        $instance = static::createFromString($string);

        $this->assertSame($result, $instance->lastIndexOfIgnoreCase($needle, $offset));
    }

    public static function provideLastIndexOfIgnoreCaseData()
    {
        return array(
            array(null, 'abc', '', 0),
            array(null, 'abc', '', -2),
            array(null, 'elegant', 'z', -1),
            array(1, 'abc', 'b', 0),
            array(1, 'abc', 'b', -1),
            array(2, 'abcdefgh', 'c', -1),
            array(2, 'abcdefgh', 'C', -1),
            array(5, 'dejaaaa', 'A', -2),
            array(5, 'DEJAAAA', 'a', -2),
            array(74, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'I', 0),
            array(19, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'I', -40),
            array(6, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'IPSUM', 0),
            array(57, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'AmeT', 0),
            array(57, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'aMEt', -10),
            array(22, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, amet sagittis felis.', 'AMET', -30),
        );
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Passing an empty delimiter is not supported by this method. Use getIterator() method instead.
     */
    public function testExplodeWithEmptyDelimiterIsNotSupported()
    {
        static::createFromString('')->explode('');
    }

    /**
     * @dataProvider provideStringToExplode
     */
    public function testExplode($string, $delimiter, array $chuncks, int $limit = null)
    {
        $this->assertEquals($chuncks, static::createFromString($string)->explode($delimiter, $limit));
    }

    public static function provideStringToExplode()
    {
        return array(
            array(
                'hello world',
                ' ',
                array(
                    static::createFromString('hello'),
                    static::createFromString('world'),
                ),
                null,
            ),
            array(
                'radar',
                'd',
                array(
                    static::createFromString('ra'),
                    static::createFromString('ar'),
                ),
                2,
            ),
            array(
                'foo,bar,baz,qux,kix',
                ',',
                array(
                    static::createFromString('foo'),
                    static::createFromString('bar'),
                    static::createFromString('baz'),
                    static::createFromString('qux'),
                    static::createFromString('kix'),
                ),
                null,
            ),
            array(
                'foo,bar,baz,qux,kix',
                ',',
                array(
                    static::createFromString('foo,bar,baz,qux,kix'),
                ),
                1,
            ),
            array(
                'foo,bar,baz,qux,kix',
                ',',
                array(
                    static::createFromString('foo'),
                    static::createFromString('bar'),
                    static::createFromString('baz,qux,kix'),
                ),
                3,
            ),
            array(
                'Quisque viverra tincidunt elit. Vestibulum convallis dui nec lacis suscipit cursus.',
                'is',
                array(
                    static::createFromString('Qu'),
                    static::createFromString('que viverra tincidunt elit. Vestibulum convall'),
                    static::createFromString(' dui nec lac'),
                    static::createFromString(' suscipit cursus.'),
                ),
                null,
            ),
        );
    }

    /**
     * @dataProvider provideInvalidIteratorLimit
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage The maximum length of each segment must be greater than zero.
     */
    public function testGetIteratorWithInvalidLimit(int $limit)
    {
        static::createFromString('foo|bar|baz')->getIterator($limit)->valid();
    }

    public static function provideInvalidIteratorLimit()
    {
        return array(
            array(-2),
            array(-1),
            array(0),
        );
    }

    /**
     * @dataProvider provideGetIteratorData
     */
    public function testGetIterator(string $string, array $chunks, int $limit)
    {
        $this->assertEquals(
            $chunks,
            iterator_to_array(static::createFromString($string)->getIterator($limit))
        );
    }

    public static function provideGetIteratorData()
    {
        return array(
            array(
                '',
                array(),
                1,
            ),
            array(
                'hello',
                array(
                    static::createFromString('h'),
                    static::createFromString('e'),
                    static::createFromString('l'),
                    static::createFromString('l'),
                    static::createFromString('o'),
                ),
                1,
            ),
            array(
                'hello you!',
                array(
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
                ),
                1,
            ),
            array(
                'hell',
                array(
                    static::createFromString('h'),
                    static::createFromString('e'),
                    static::createFromString('l'),
                    static::createFromString('l'),
                ),
                1,
            ),
            array(
                'hell',
                array(
                    static::createFromString('he'),
                    static::createFromString('ll'),
                ),
                2,
            ),
        );
    }

    /**
     * @dataProvider provideLowercaseData
     */
    public function testToLowerCase(string $expected, string $origin)
    {
        $instance = static::createFromString($origin)->toLowerCase();

        $this->assertNotSame(static::createFromString($origin), $instance);
        $this->assertEquals(static::createFromString($expected), $instance);
        $this->assertSame($expected, (string) $instance);
    }

    public static function provideLowercaseData()
    {
        return array(
            array('hello world', 'hello world'),
            array('hello world', 'HELLO WORLD'),
            array('hello world', 'Hello World'),
            array('symfony', 'symfony'),
            array('symfony', 'Symfony'),
            array('symfony', 'sYmFOny'),
        );
    }

    /**
     * @dataProvider provideUppercaseData
     */
    public function testToUpperCase(string $expected, string $origin)
    {
        $instance = static::createFromString($origin)->toUpperCase();

        $this->assertNotSame(static::createFromString($origin), $instance);
        $this->assertEquals(static::createFromString($expected), $instance);
        $this->assertSame($expected, (string) $instance);
    }

    public static function provideUppercaseData()
    {
        return array(
            array('HELLO WORLD', 'hello world'),
            array('HELLO WORLD', 'HELLO WORLD'),
            array('HELLO WORLD', 'Hello World'),
            array('SYMFONY', 'symfony'),
            array('SYMFONY', 'Symfony'),
            array('SYMFONY', 'sYmFOny'),
        );
    }

    /**
     * @dataProvider provideUpperCaseFirstData
     */
    public function testToUpperCaseFirst(string $expected, string $origin, bool $allWords)
    {
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->toUpperCaseFirst($allWords)
        );
    }

    public static function provideUpperCaseFirstData()
    {
        return array(
            array('Hello world', 'hello world', false),
            array('Hello World', 'hello world', true),
            array('HELLO WORLD', 'HELLO WORLD', false),
            array('HELLO WORLD', 'HELLO WORLD', true),
            array('HELLO wORLD', 'hELLO wORLD', false),
            array('HELLO WORLD', 'hELLO wORLD', true),
            array('Symfony', 'symfony', false),
            array('Symfony', 'Symfony', false),
            array('SYmFOny', 'sYmFOny', false),
        );
    }

    /**
     * @dataProvider provideSubstrData
     */
    public function testSubstr(string $expected, string $origin, int $start, int $length = null)
    {
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->substr($start, $length)
        );
    }

    public static function provideSubstrData()
    {
        return array(
            array('Symfony', 'Symfony is awesome', 0, 7),
            array(' ', 'Symfony is awesome', 7, 1),
            array('is', 'Symfony is awesome', 8, 2),
            array(' ', 'Symfony is awesome', 10, 1),
            array('awesome', 'Symfony is awesome', 11, 7),
        );
    }

    /**
     * @dataProvider provideSuffixToAppend
     */
    public function testAppendString(string $expected, array $suffixes)
    {
        $instance = static::createFromString('');
        foreach ($suffixes as $suffix) {
            $instance = $instance->append($suffix);
        }

        $this->assertEquals(static::createFromString($expected), $instance);
    }

    public static function provideSuffixToAppend()
    {
        return array(
            array(
                'Symfony',
                array('Sym', 'fony'),
            ),
            array(
                'Hello World!',
                array('Hel', 'lo', ' ', 'World', '!'),
            ),
        );
    }

    /**
     * @dataProvider providePrefixToPrepend
     */
    public function testPrependString(string $expected, array $prefixes)
    {
        $instance = static::createFromString('');
        foreach ($prefixes as $suffix) {
            $instance = $instance->prepend($suffix);
        }

        $this->assertEquals(static::createFromString($expected), $instance);
    }

    public static function providePrefixToPrepend()
    {
        return array(
            array(
                'Symfony',
                array('fony', 'Sym'),
            ),
            array(
                'Hello World!',
                array('!', 'World', ' ', 'lo', 'Hel'),
            ),
        );
    }

    /**
     * @dataProvider provideTrimData
     */
    public function testTrim(string $expected, string $origin, string $chars = null)
    {
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->trim($chars)
        );
    }

    public static function provideTrimData()
    {
        return array(
            array(
                "Symfony     IS GREAT\t!!!",
                "    Symfony     IS GREAT\t!!!\n",
                null,
            ),
            array(
                "Symfony     IS GREAT\t!!!\n",
                "    Symfony     IS GREAT\t!!!\n",
                ' ',
            ),
            array(
                "    Symfony     IS GREAT\t!!!",
                "    Symfony     IS GREAT\t!!!\n",
                "\n",
            ),
            array(
                "Symfony     IS GREAT\t",
                "    Symfony     IS GREAT\t!!!\n",
                " \n!",
            ),
        );
    }

    /**
     * @dataProvider provideTrimLeftData
     */
    public function testTrimLeft(string $expected, string $origin, string $chars = null)
    {
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->trimLeft($chars)
        );
    }

    public static function provideTrimLeftData()
    {
        return array(
            array(
                "<span>Symfony is a PHP framework</span>\n",
                "\n\t<span>Symfony is a PHP framework</span>\n",
                null,
            ),
            array(
                "\t<span>Symfony is a PHP framework</span>\n",
                "\n\t<span>Symfony is a PHP framework</span>\n",
                "\n",
            ),
        );
    }

    /**
     * @dataProvider provideTrimRightData
     */
    public function testTrimRight(string $expected, string $origin, string $chars = null)
    {
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->trimRight($chars)
        );
    }

    public static function provideTrimRightData()
    {
        return array(
            array(
                "\n\t<span>Symfony is a PHP framework</span>",
                "\n\t<span>Symfony is a PHP framework</span>  \n",
                null,
            ),
            array(
                "\n\t<span>Symfony is a PHP framework</span>  ",
                "\n\t<span>Symfony is a PHP framework</span>  \n",
                "\n",
            ),
        );
    }

    /**
     * @dataProvider provideReverseData
     */
    public function testReverse(string $expected, string $origin)
    {
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->reverse()
        );
    }

    public static function provideReverseData()
    {
        return array(
            array('foo', 'oof'),
            array('bar', 'rab'),
            array('foo bar', 'rab oof'),
        );
    }

    /**
     * @dataProvider provideSubstringOfData
     */
    public function testSubstringOf($expected, string $needle, string $origin, bool $beforeNeedle)
    {
        $this->assertEquals(
            $expected,
            static::createFromString($origin)->substringOf($needle, $beforeNeedle)
        );
    }

    public static function provideSubstringOfData()
    {
        return array(
            array(null, '', 'hello world', true),
            array(null, '', 'hello world', false),
            array(null, 'w', 'hello World', true),
            array(null, 'w', 'hello World', false),
            array(static::createFromString('world'), 'w', 'hello world', false),
            array(static::createFromString('hello '), 'w', 'hello world', true),
        );
    }

    /**
     * @dataProvider provideSubstringOfIgnoreCaseData
     */
    public function testSubstringOfIgnoreCase($expected, string $needle, string $origin, int $offset)
    {
        $this->assertEquals(
            $expected,
            static::createFromString($origin)->substringOfIgnoreCase($needle, $offset)
        );
    }

    public static function provideSubstringOfIgnoreCaseData()
    {
        return array(
            array(null, '', 'hello world', false),
            array(null, '', 'hello world', true),
            array(null, 'foo', 'hello world', false),
            array(null, 'foo', 'hello world', true),
            array(static::createFromString('world'), 'w', 'hello world', false),
            array(static::createFromString('hello '), 'w', 'hello world', true),
            array(static::createFromString('world'), 'W', 'hello world', false),
            array(static::createFromString('hello '), 'W', 'hello world', true),
        );
    }

    /**
     * @dataProvider provideLastSubstringOfData
     */
    public function testLastSubstringOf($expected, string $needle, string $origin, int $offset)
    {
        $this->assertEquals(
            $expected,
            static::createFromString($origin)->lastSubstringOf($needle, $offset)
        );
    }

    public static function provideLastSubstringOfData()
    {
        return array(
            array(null, '', 'hello world', false),
            array(null, '', 'hello world', true),
            array(null, 'L', 'hello world', false),
            array(null, 'L', 'hello world', true),
            array(static::createFromString('ld'), 'l', 'hello world', false),
            array(static::createFromString('hello wor'), 'l', 'hello world', true),
            array(static::createFromString('orld'), 'o', 'hello world', false),
            array(static::createFromString('hello w'), 'o', 'hello world', true),
        );
    }

    /**
     * @dataProvider provideLastSubstringOfIgnoreCaseData
     */
    public function testLastSubstringOfIgnoreCase($expected, string $needle, string $origin, int $offset)
    {
        $this->assertEquals(
            $expected,
            static::createFromString($origin)->lastSubstringOfIgnoreCase($needle, $offset)
        );
    }

    public static function provideLastSubstringOfIgnoreCaseData()
    {
        return array(
            array(null, '', 'hello world', false),
            array(null, '', 'hello world', true),
            array(null, 'FOO', 'hello world', false),
            array(null, 'FOO', 'hello world', true),
            array(static::createFromString('ld'), 'l', 'hello world', false),
            array(static::createFromString('hello wor'), 'l', 'hello world', true),
            array(static::createFromString('ld'), 'L', 'hello world', false),
            array(static::createFromString('hello wor'), 'L', 'hello world', true),
            array(static::createFromString('orld'), 'O', 'hello world', false),
            array(static::createFromString('hello w'), 'O', 'hello world', true),
        );
    }

    /**
     * @dataProvider provideWidthData
     */
    public function testWidth(int $width, string $string)
    {
        $this->assertSame($width, static::createFromString($string)->width());
    }

    public static function provideWidthData()
    {
        return array(
            array(1, "\x1B[32mZ\x1B[0m\x1B[m"),
            array(7, "Foo\rBar Baz"),
        );
    }

    /**
     * @dataProvider provideToFoldedCaseData
     */
    public function testToFoldedCase(string $expected, string $origin)
    {
        $this->assertEquals(
            static::createFromString($expected),
            static::createFromString($origin)->toFoldedCase()
        );
    }

    public static function provideToFoldedCaseData()
    {
        return array(
            array('hello', 'HELlo'),
            array('world', 'worLd'),
        );
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage The number of search patterns does not match the number of pattern replacements.
     */
    public function testReplaceAllCannotAcceptPatternsAndReplacementsArrayOfDifferentSizes()
    {
        static::createFromString('baobab')->replaceAll(array('a', 'b', 'o'), array('x', 'y'));
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Search pattern at key 0 must be a valid string.
     */
    public function testReplaceAllCannotAcceptInvalidSearchPattern()
    {
        static::createFromString('foo')->replaceAll(array(1), array('2'));
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Pattern replacement at key 0 must be a valid string.
     */
    public function testReplaceAllCannotAcceptInvalidPatternReplacement()
    {
        static::createFromString('foo')->replaceAll(array('f'), array(1));
    }

    /**
     * @dataProvider provideReplaceData
     */
    public function testReplace(string $expectedString, int $expectedCount, string $origin, string $from, string $to)
    {
        $origin = static::createFromString($origin);

        $count = 0;
        $result = $origin->replace($from, $to, $count);

        $this->assertEquals(static::createFromString($expectedString), $result);
        $this->assertSame($expectedCount, $count);
    }

    public static function provideReplaceData()
    {
        return array(
            array('hello world', 0, 'hello world', '', ''),
            array('hello world', 0, 'hello world', '', '_'),
            array('helloworld',  1, 'hello world', ' ', ''),
            array('hello_world', 1, 'hello world', ' ', '_'),
            array('hemmo wormd', 3, 'hello world', 'l', 'm'),
            array('hello world', 0, 'hello world', 'L', 'm'),
        );
    }

    /**
     * @dataProvider provideReplaceAllData
     */
    public function testReplaceAll(string $expectedString, int $expectedCount, string $origin, array $from, array $to)
    {
        $origin = static::createFromString($origin);

        $count = 0;
        $result = $origin->replaceAll($from, $to, $count);

        $this->assertEquals(static::createFromString($expectedString), $result);
        $this->assertSame($expectedCount, $count);
    }

    public static function provideReplaceAllData()
    {
        return array(
            array('hemma warmd', 5, 'hello world', array('o', 'l'), array('a', 'm')),
        );
    }

    /**
     * @dataProvider provideReplaceIgnoreCaseData
     */
    public function testReplaceIgnoreCase(string $expectedString, int $expectedCount, string $origin, string $from, string $to)
    {
        $origin = static::createFromString($origin);

        $count = 0;
        $result = $origin->replaceIgnoreCase($from, $to, $count);

        $this->assertEquals(static::createFromString($expectedString), $result);
        $this->assertSame($expectedCount, $count);
    }

    public static function provideReplaceIgnoreCaseData()
    {
        return array(
            array('hello world', 0, 'hello world', '', ''),
            array('hello world', 0, 'hello world', '', '_'),
            array('helloworld',  1, 'hello world', ' ', ''),
            array('hello_world', 1, 'hello world', ' ', '_'),
            array('hemmo wormd', 3, 'hello world', 'l', 'm'),
            array('heMMo worMd', 3, 'hello world', 'L', 'M'),
        );
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage The number of search patterns does not match the number of pattern replacements.
     */
    public function testReplaceAllIgnoreCaseCannotAcceptPatternsAndReplacementsArrayOfDifferentSizes()
    {
        static::createFromString('baobab')->replaceAllIgnoreCase(array('a', 'b', 'o'), array('x', 'y'));
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Search pattern at key 0 must be a valid string.
     */
    public function testReplaceAllIgnoreCaseCannotAcceptInvalidSearchPattern()
    {
        static::createFromString('foo')->replaceAllIgnoreCase(array(1), array('2'));
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Pattern replacement at key 0 must be a valid string.
     */
    public function testReplaceAllIgnoreCaseCannotAcceptInvalidPatternReplacement()
    {
        static::createFromString('foo')->replaceAllIgnoreCase(array('f'), array(1));
    }

    /**
     * @dataProvider provideReplaceAllIgnoreCaseData
     */
    public function testReplaceAllIgnoreCase(string $expectedString, int $expectedCount, string $origin, array $from, array $to)
    {
        $origin = static::createFromString($origin);

        $count = 0;
        $result = $origin->replaceAllIgnoreCase($from, $to, $count);

        $this->assertEquals(static::createFromString($expectedString), $result);
        $this->assertSame($expectedCount, $count);
    }

    public static function provideReplaceAllIgnoreCaseData()
    {
        return array(
            array('hemma warmd', 5, 'hello world', array('o', 'l'), array('a', 'm')),
        );
    }
}
