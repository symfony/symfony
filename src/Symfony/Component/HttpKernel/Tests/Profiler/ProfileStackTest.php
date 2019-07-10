<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\ProfileStack;

class ProfileStackTest extends TestCase
{
    public function test()
    {
        $profileStack = new ProfileStack();

        $this->assertFalse($profileStack->has($request = new Request()));

        $profileStack->set($request, $profile = new Profile('foo'));

        $this->assertTrue($profileStack->has($request));

        $this->assertSame($profile, $profileStack->get($request));

        $profileStack->reset();

        $this->assertFalse($profileStack->has($request));
    }

    public function testGetWitUnknownRequest()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There is no profile in the stack for the passed request.');

        (new ProfileStack())->get(new Request());
    }
}
