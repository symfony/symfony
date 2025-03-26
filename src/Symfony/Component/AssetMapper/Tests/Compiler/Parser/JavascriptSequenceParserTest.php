<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Tests\Compiler\Parser;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\Compiler\Parser\JavascriptSequenceParser;

class JavascriptSequenceParserTest extends TestCase
{
    public function testParseEmptyContent()
    {
        $parser = new JavascriptSequenceParser('');

        $this->assertTrue($parser->isExecutable());
    }

    public function testItThrowsWhenOutOfBounds()
    {
        $parser = new JavascriptSequenceParser('');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse beyond the end of the content.');

        $parser->parseUntil(1);
    }

    public function testItThrowWhenBackward()
    {
        $parser = new JavascriptSequenceParser('  ');

        $parser->parseUntil(2);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot parse backwards.');

        $parser->parseUntil(1);
    }

    public function testParseToTheEnd()
    {
        $parser = new JavascriptSequenceParser('123');
        $parser->parseUntil(3);

        $this->assertTrue($parser->isExecutable());
    }

    /**
     * @dataProvider provideSequenceCases
     */
    public function testParseSequence(string $content, int $position, bool $isExcecutable)
    {
        $parser = new JavascriptSequenceParser($content);
        $parser->parseUntil($position);

        $this->assertSame($isExcecutable, $parser->isExecutable());
    }

    /**
     * @return iterable<array{string, int, string}>
     */
    public static function provideSequenceCases(): iterable
    {
        yield 'empty' => [
            '',
            0,
            true,
        ];
        yield 'inline comment' => [
            '//',
            2,
            false,
        ];
        yield 'comment' => [
            '/* */',
            2,
            false,
        ];
        yield 'after comment' => [
            '/* */',
            5,
            true,
        ];
        yield 'multi-line comment' => [
            '/**
              abc
              */',
            2,
            false,
        ];
        yield 'after multi-line comment' => [
            "/** \n */ abc",
            8,
            true,
        ];
    }

    /**
     * @dataProvider provideCommentCases
     */
    public function testIdentifyComment(string $content, int $position, bool $isComment)
    {
        $parser = new JavascriptSequenceParser($content);
        $parser->parseUntil($position);

        $this->assertSame($isComment, $parser->isComment());
        $this->assertSame(!$isComment, $parser->isExecutable());
    }

    /**
     * @return iterable<array{string, int, string}>
     */
    public static function provideCommentCases(): iterable
    {
        yield 'empty' => [
            '',
            0,
            false,
        ];
        yield 'inline comment' => [
            '//',
            2,
            true,
        ];
        yield 'comment' => [
            '/* */',
            2,
            true,
        ];
        yield 'multi-line comment' => [
            '/**
              abc
              */',
            2,
            true,
        ];
        yield 'after multi-line comment' => [
            "/** \n */ abc",
            8,
            false,
        ];
        yield 'after comment' => [
            '/* */',
            5,
            false,
        ];
        yield 'comment after comment' => [
            '/* */ //',
            7,
            true,
        ];
        yield 'comment after multi-line comment' => [
            '/* */ /**/',
            8,
            true,
        ];
        yield 'multi-line comment after comment' => [
            '// /* */',
            8,
            true,
        ];
    }

    /**
     * @dataProvider provideStringCases
     */
    public function testIdentifyStrings(string $content, int $position, bool $isString)
    {
        $parser = new JavascriptSequenceParser($content);
        $parser->parseUntil($position);

        $this->assertSame($isString, $parser->isString());
    }

    /**
     * @return iterable<array{string, int, string}>
     */
    public static function provideStringCases(): iterable
    {
        yield 'empty' => [
            '',
            0,
            false,
        ];
        yield 'before single quote' => [
            " '",
            0,
            false,
        ];
        yield 'on single quote' => [
            "'",
            0,
            true,
        ];
        yield 'between single quotes' => [
            "' '",
            2,
            true,
        ];
        yield 'after single quote' => [
            "'' ",
            3,
            false,
        ];
        yield 'before double quote' => [
            ' "',
            0,
            false,
        ];
        yield 'on double quote' => [
            '"',
            0,
            true,
        ];
        yield 'between double quotes' => [
            '" "',
            2,
            true,
        ];
        yield 'after double quote' => [
            '"" ',
            3,
            false,
        ];
    }
}
