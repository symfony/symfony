<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\Parser\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\CssSelector\Parser\Reader;
use Symfony\Component\CssSelector\Parser\Token;
use Symfony\Component\CssSelector\Parser\TokenStream;

/**
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
abstract class AbstractHandlerTest extends TestCase
{
    /** @dataProvider getHandleValueTestData */
    public function testHandleValue($value, Token $expectedToken, $remainingContent)
    {
        $reader = new Reader($value);
        $stream = new TokenStream();

        self::assertTrue($this->generateHandler()->handle($reader, $stream));
        self::assertEquals($expectedToken, $stream->getNext());
        $this->assertRemainingContent($reader, $remainingContent);
    }

    /** @dataProvider getDontHandleValueTestData */
    public function testDontHandleValue($value)
    {
        $reader = new Reader($value);
        $stream = new TokenStream();

        self::assertFalse($this->generateHandler()->handle($reader, $stream));
        $this->assertStreamEmpty($stream);
        $this->assertRemainingContent($reader, $value);
    }

    abstract public function getHandleValueTestData();

    abstract public function getDontHandleValueTestData();

    abstract protected function generateHandler();

    protected function assertStreamEmpty(TokenStream $stream)
    {
        $property = new \ReflectionProperty($stream, 'tokens');
        $property->setAccessible(true);

        self::assertEquals([], $property->getValue($stream));
    }

    protected function assertRemainingContent(Reader $reader, $remainingContent)
    {
        if ('' === $remainingContent) {
            self::assertEquals(0, $reader->getRemainingLength());
            self::assertTrue($reader->isEOF());
        } else {
            self::assertEquals(\strlen($remainingContent), $reader->getRemainingLength());
            self::assertEquals(0, $reader->getOffset($remainingContent));
        }
    }
}
