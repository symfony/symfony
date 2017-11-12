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
    public function testHandleValue($value, Token $expectedToken, $remainingContent): void
    {
        $reader = new Reader($value);
        $stream = new TokenStream();

        $this->assertTrue($this->generateHandler()->handle($reader, $stream));
        $this->assertEquals($expectedToken, $stream->getNext());
        $this->assertRemainingContent($reader, $remainingContent);
    }

    /** @dataProvider getDontHandleValueTestData */
    public function testDontHandleValue($value): void
    {
        $reader = new Reader($value);
        $stream = new TokenStream();

        $this->assertFalse($this->generateHandler()->handle($reader, $stream));
        $this->assertStreamEmpty($stream);
        $this->assertRemainingContent($reader, $value);
    }

    abstract public function getHandleValueTestData(): void;

    abstract public function getDontHandleValueTestData(): void;

    abstract protected function generateHandler(): void;

    protected function assertStreamEmpty(TokenStream $stream): void
    {
        $property = new \ReflectionProperty($stream, 'tokens');
        $property->setAccessible(true);

        $this->assertEquals(array(), $property->getValue($stream));
    }

    protected function assertRemainingContent(Reader $reader, $remainingContent): void
    {
        if ('' === $remainingContent) {
            $this->assertEquals(0, $reader->getRemainingLength());
            $this->assertTrue($reader->isEOF());
        } else {
            $this->assertEquals(strlen($remainingContent), $reader->getRemainingLength());
            $this->assertEquals(0, $reader->getOffset($remainingContent));
        }
    }
}
