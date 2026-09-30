<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Attribute\Lock;

class LockTest extends TestCase
{
    public function testDefaults()
    {
        $lock = new Lock();

        $this->assertNull($lock->key);
        $this->assertSame('default', $lock->factory);
        $this->assertSame(30.0, $lock->ttl);
        $this->assertFalse($lock->blocking);
        $this->assertSame([], $lock->methods);
    }

    public function testTtlMustBePositive()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The "$ttl" argument of "Symfony\\Component\\HttpKernel\\Attribute\\Lock" must be greater than 0 or null, "0" given.');

        new Lock(ttl: 0);
    }

    public function testNullTtlMeansNoExpiration()
    {
        $this->assertNull(new Lock(ttl: null)->ttl);
    }

    public function testMethodsAreNormalized()
    {
        $lock = new Lock(methods: ['get', 'post']);

        $this->assertSame(['GET', 'POST', 'HEAD'], $lock->methods);
    }

    public function testSingleMethodIsWrapped()
    {
        $this->assertSame(['POST'], new Lock(methods: 'post')->methods);
    }
}
