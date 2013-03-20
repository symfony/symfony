<?php

namespace Symfony\Component\CssSelector\Tests\Handler;

use Symfony\Component\CssSelector\Parser\Handler\CommentHandler;
use Symfony\Component\CssSelector\Parser\Reader;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\TokenStream;

class CommentHandlerTest extends AbstractHandlerTest
{
    /** @dataProvider getHandleValueTestData */
    public function testHandleValue($value, $remainingContent)
    {
        $reader = new Reader($value);
        $stream = new TokenStream();

        $this->assertTrue($this->generateHandler()->handle($reader, $stream));
        // comments are ignored (not pushed as token in stream)
        $this->assertStreamEmpty($stream);
        $this->assertRemainingContent($reader, $remainingContent);
    }

    public function getHandleValueTestData()
    {
        return array(
            array('/* comment */', ''),
            array('/* comment */foo', 'foo'),
        );
    }

    public function getDontHandleValueTestData()
    {
        return array(
            array('>'),
            array('+'),
            array(' '),
        );
    }

    protected function generateHandler()
    {
        return new CommentHandler();
    }
}
