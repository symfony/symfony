<?php

namespace Symfony\Component\CssSelector\Tests\Handler;

use Symfony\Component\CssSelector\Parser\Handler\WhitespaceHandler;
use Symfony\Component\CssSelector\Parser\Reader;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\TokenStream;

class WhitespaceHandlerTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getHandledValueTestData */
    public function testHandledValue($value)
    {
        $handler = new WhitespaceHandler();
        $stream = new TokenStream();

        $this->assertTrue($handler->handle(new Reader($value), $stream));
        $this->assertEquals(new Token(Token::TYPE_WHITESPACE, $value, 0), $stream->getNext());
    }

    /** @dataProvider getUnhandledValueTestData */
    public function testUnhandledValue($value)
    {
        $handler = new WhitespaceHandler();
        $stream = new TokenStream();

        $this->assertFalse($handler->handle(new Reader($value), $stream));
        $this->setExpectedException('Symfony\Component\CssSelector\Exception\InternalErrorException');
        $stream->getNext();
    }

    public function getHandledValueTestData()
    {
        return array(
            array(' '),
            array("\n"),
            array("\t"),
        );
    }

    public function getUnhandledValueTestData()
    {
        return array(
            array('>'),
            array('1'),
            array('a'),
        );
    }
}
