<?php

namespace Symfony\Component\CssSelector\Tests\Handler;

use Symfony\Component\CssSelector\Parser\Handler\StringHandler;
use Symfony\Component\CssSelector\Parser\Reader;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\TokenStream;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerPatterns;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerEscaping;

class StringHandlerTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getHandledValueTestData */
    public function testHandledValue($value)
    {
        $patterns = new TokenizerPatterns();
        $handler = new StringHandler($patterns, new TokenizerEscaping($patterns));
        $stream = new TokenStream();

        $this->assertTrue($handler->handle(new Reader($value), $stream));
        $this->assertEquals(new Token(Token::TYPE_STRING, substr($value, 1, -1), 1), $stream->getNext());
    }

    /** @dataProvider getUnhandledValueTestData */
    public function testUnhandledValue($value)
    {
        $patterns = new TokenizerPatterns();
        $handler = new StringHandler($patterns, new TokenizerEscaping($patterns));
        $stream = new TokenStream();

        $this->assertFalse($handler->handle(new Reader($value), $stream));
        $this->setExpectedException('Symfony\Component\CssSelector\Exception\InternalErrorException');
        $stream->getNext();
    }

    public function getHandledValueTestData()
    {
        return array(
            array('"hello"'),
            array('"1"'),
            array('" "'),
            array('""'),
            array("'hello'"),
        );
    }

    public function getUnhandledValueTestData()
    {
        return array(
            array('hello'),
            array('>'),
            array('1'),
            array(' '),
        );
    }
}
