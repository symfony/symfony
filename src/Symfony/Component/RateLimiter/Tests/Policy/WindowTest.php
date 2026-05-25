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
