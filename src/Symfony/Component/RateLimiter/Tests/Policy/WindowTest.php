<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RateLimiter\Tests\Policy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\RateLimiter\Policy\Window;

class WindowTestToStringGadget
{
    public static bool $fired = false;

    public function __toString(): string
    {
        self::$fired = true;

        return '';
    }
}

class WindowTest extends TestCase
{
    public function testSerializePreservesLargeHitCount()
    {
        if (\PHP_INT_SIZE < 8) {
            $this->markTestSkipped('Requires 64-bit integers.');
        }

        $window = new Window('test', 60, 4294967296, 1000.5);
        $window->add(4294967296, 1000.5);

        $this->assertEquals($window, unserialize(serialize($window)));

        $window->add(-1, 1000.5);

        $this->assertEquals($window, unserialize(serialize($window)));
        $this->assertSame([
            'test' => 1000.5,
            pack('NN', 4294967295, 60) => 4294967296,
        ], $window->__serialize());
    }

    public function testSerializeKeepsCompactRepresentation()
    {
        $window = new Window('test', 60, 10, 1000.5);
        $window->add(7, 1000.5);

        $this->assertSame([
            'test' => 1000.5,
            pack('NN', 7, 60) => 10,
        ], $window->__serialize());
        $this->assertEquals($window, unserialize(serialize($window)));
    }

    public function testUnserializeLegacyRepresentation()
    {
        $window = new Window('test', 60, 10, 1000.5);
        $window->__unserialize(['legacy', 7, 120, 20, 2000.5]);

        $this->assertSame('legacy', $window->getId());
        $this->assertSame(7, $window->getHitCount());
        $this->assertSame(13, $window->getAvailableTokens(2000.5));
        $this->assertSame(120, $window->getExpirationTime());
        $this->assertEquals($window, unserialize(serialize($window)));
    }

    public function testUnserializeRejectsObjectInTypedIdProperty()
    {
        $data = [
            'id' => new WindowTestToStringGadget(),
            'hitCount' => 0,
            'intervalInSeconds' => 1,
            'maxSize' => 10,
            'timer' => 0.0,
        ];
        $payload = \sprintf('O:%d:"%s":%d:{', \strlen(Window::class), Window::class, \count($data));
        foreach ($data as $key => $value) {
            $payload .= serialize($key).serialize($value);
        }
        $payload .= '}';
        WindowTestToStringGadget::$fired = false;

        try {
            unserialize($payload);
            $this->fail('Expected BadMethodCallException.');
        } catch (\BadMethodCallException $e) {
        }

        $this->assertFalse(WindowTestToStringGadget::$fired, '__toString gadget must not fire during unserialize');
    }
}
