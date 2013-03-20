<?php

namespace Symfony\Component\CssSelector\Tests\Handler;

use Symfony\Component\CssSelector\Parser\Handler\IdentifierHandler;
use Symfony\Component\CssSelector\Parser\Reader;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\TokenStream;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerPatterns;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerEscaping;

class IdentifierHandlerTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getHandledValueTestData */
    public function testHandledValue($value)
    {
        $patterns = new TokenizerPatterns();
        $handler = new IdentifierHandler($patterns, new TokenizerEscaping($patterns));
        $stream = new TokenStream();

        $this->assertTrue($handler->handle(new Reader($value), $stream));
        $this->assertEquals(new Token(Token::TYPE_IDENTIFIER, $value, 0), $stream->getNext());
    }

    /** @dataProvider getUnhandledValueTestData */
    public function testUnhandledValue($value)
    {
        $patterns = new TokenizerPatterns();
        $handler = new IdentifierHandler($patterns, new TokenizerEscaping($patterns));
        $stream = new TokenStream();

        $this->assertFalse($handler->handle(new Reader($value), $stream));
        $this->setExpectedException('Symfony\Component\CssSelector\Exception\InternalErrorException');
        $stream->getNext();
    }

    public function getHandledValueTestData()
    {
        return array(
            array('h1'),
        );
    }

    public function getUnhandledValueTestData()
    {
        return array(
            array('>'),
            array('+'),
            array(' '),
        );
    }
}
