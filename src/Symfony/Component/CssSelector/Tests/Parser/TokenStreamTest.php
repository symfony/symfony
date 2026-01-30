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
    public function testGetNext(): void
    {
        $stream = new TokenStream();
        $stream->push($t1 = new Token(Token::TYPE_IDENTIFIER, 'h1', 0));
        $stream->push($t2 = new Token(Token::TYPE_DELIMITER, '.', 2));
        $stream->push($t3 = new Token(Token::TYPE_IDENTIFIER, 'title', 3));

        $this->assertSame($t1, $stream->getNext());
        $this->assertSame($t2, $stream->getNext());
        $this->assertSame($t3, $stream->getNext());
    }

    public function testGetPeek(): void
    {
        $stream = new TokenStream();
        $stream->push($t1 = new Token(Token::TYPE_IDENTIFIER, 'h1', 0));
        $stream->push($t2 = new Token(Token::TYPE_DELIMITER, '.', 2));
        $stream->push($t3 = new Token(Token::TYPE_IDENTIFIER, 'title', 3));

        $this->assertSame($t1, $stream->getPeek());
        $this->assertSame($t1, $stream->getNext());
        $this->assertSame($t2, $stream->getPeek());
        $this->assertSame($t2, $stream->getPeek());
        $this->assertSame($t2, $stream->getNext());
    }

    public function testGetNextIdentifier(): void
    {
        $stream = new TokenStream();
        $stream->push(new Token(Token::TYPE_IDENTIFIER, 'h1', 0));

        $this->assertEquals('h1', $stream->getNextIdentifier());
    }

    public function testFailToGetNextIdentifier(): void
    {
        $stream = new TokenStream();
        $stream->push(new Token(Token::TYPE_DELIMITER, '.', 2));

        $this->expectException(SyntaxErrorException::class);

        $stream->getNextIdentifier();
    }

    public function testGetNextIdentifierOrStar(): void
    {
        $stream = new TokenStream();

        $stream->push(new Token(Token::TYPE_IDENTIFIER, 'h1', 0));
        $this->assertEquals('h1', $stream->getNextIdentifierOrStar());

        $stream->push(new Token(Token::TYPE_DELIMITER, '*', 0));
        $this->assertNull($stream->getNextIdentifierOrStar());
    }

    public function testFailToGetNextIdentifierOrStar(): void
    {
        $stream = new TokenStream();
        $stream->push(new Token(Token::TYPE_DELIMITER, '.', 2));

        $this->expectException(SyntaxErrorException::class);

        $stream->getNextIdentifierOrStar();
    }

    public function testSkipWhitespace(): void
    {
        $stream = new TokenStream();
        $stream->push($t1 = new Token(Token::TYPE_IDENTIFIER, 'h1', 0));
        $stream->push($t2 = new Token(Token::TYPE_WHITESPACE, ' ', 2));
        $stream->push($t3 = new Token(Token::TYPE_IDENTIFIER, 'h1', 3));

        $stream->skipWhitespace();
        $this->assertSame($t1, $stream->getNext());

        $stream->skipWhitespace();
        $this->assertSame($t3, $stream->getNext());
    }
}
