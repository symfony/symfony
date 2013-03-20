<?php

namespace Symfony\Component\CssSelector\Tests\Handler;

use Symfony\Component\CssSelector\Parser\Handler\HashHandler;
use Symfony\Component\CssSelector\Parser\Reader;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\TokenStream;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerPatterns;
use Symfony\Component\CssSelector\Parser\Tokenizer\TokenizerEscaping;

class HashHandlerTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getHandledValueTestData */
    public function testHandledValue($value)
    {
        $patterns = new TokenizerPatterns();
        $handler = new HashHandler($patterns, new TokenizerEscaping($patterns));
        $stream = new TokenStream();

        $this->assertTrue($handler->handle(new Reader($value), $stream));
        $this->assertEquals(new Token(Token::TYPE_HASH, substr($value, 1), 0), $stream->getNext());
    }

    /** @dataProvider getUnhandledValueTestData */
    public function testUnhandledValue($value)
    {
        $patterns = new TokenizerPatterns();
        $handler = new HashHandler($patterns, new TokenizerEscaping($patterns));
        $stream = new TokenStream();

        $this->assertFalse($handler->handle(new Reader($value), $stream));
        $this->setExpectedException('Symfony\Component\CssSelector\Exception\InternalErrorException');
        $stream->getNext();
    }

    public function getHandledValueTestData()
    {
        return array(
            array('#id'),
            array('#123'),
        );
    }

    public function getUnhandledValueTestData()
    {
        return array(
            array('id'),
            array('123'),
            array('<'),
            array('<'),
            array('#'),
        );
    }
}
