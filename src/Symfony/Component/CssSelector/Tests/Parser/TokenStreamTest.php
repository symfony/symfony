<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\TokenStream;

class TokenStreamTest extends TestCase
{
    public function testGetNext()
    {
        $stream = new TokenStream();
        $stream->push($t1 = new Token(Token::TYPE_IDENTIFIER, 'h1', 0));
        $stream->push($t2 = new Token(Token::TYPE_DELIMITER, '.', 2));
        $stream->push($t3 = new Token(Token::TYPE_IDENTIFIER, 'title', 3));

        self::assertSame($t1, $stream->getNext());
        self::assertSame($t2, $stream->getNext());
        self::assertSame($t3, $stream->getNext());
    }

    public function testGetPeek()
    {
        $stream = new TokenStream();
        $stream->push($t1 = new Token(Token::TYPE_IDENTIFIER, 'h1', 0));
        $stream->push($t2 = new Token(Token::TYPE_DELIMITER, '.', 2));
        $stream->push($t3 = new Token(Token::TYPE_IDENTIFIER, 'title', 3));

        self::assertSame($t1, $stream->getPeek());
        self::assertSame($t1, $stream->getNext());
        self::assertSame($t2, $stream->getPeek());
        self::assertSame($t2, $stream->getPeek());
        self::assertSame($t2, $stream->getNext());
    }

    public function testGetNextIdentifier()
    {
        $stream = new TokenStream();
        $stream->push(new Token(Token::TYPE_IDENTIFIER, 'h1', 0));

        self::assertEquals('h1', $stream->getNextIdentifier());
    }

    public function testFailToGetNextIdentifier()
    {
        self::expectException(SyntaxErrorException::class);

        $stream = new TokenStream();
        $stream->push(new Token(Token::TYPE_DELIMITER, '.', 2));
        $stream->getNextIdentifier();
    }

    public function testGetNextIdentifierOrStar()
    {
        $stream = new TokenStream();

        $stream->push(new Token(Token::TYPE_IDENTIFIER, 'h1', 0));
        self::assertEquals('h1', $stream->getNextIdentifierOrStar());

        $stream->push(new Token(Token::TYPE_DELIMITER, '*', 0));
        self::assertNull($stream->getNextIdentifierOrStar());
    }

    public function testFailToGetNextIdentifierOrStar()
    {
        self::expectException(SyntaxErrorException::class);

        $stream = new TokenStream();
        $stream->push(new Token(Token::TYPE_DELIMITER, '.', 2));
        $stream->getNextIdentifierOrStar();
    }

    public function testSkipWhitespace()
    {
        $stream = new TokenStream();
        $stream->push($t1 = new Token(Token::TYPE_IDENTIFIER, 'h1', 0));
        $stream->push($t2 = new Token(Token::TYPE_WHITESPACE, ' ', 2));
        $stream->push($t3 = new Token(Token::TYPE_IDENTIFIER, 'h1', 3));

        $stream->skipWhitespace();
        self::assertSame($t1, $stream->getNext());

        $stream->skipWhitespace();
        self::assertSame($t3, $stream->getNext());
    }
}
