<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Kernel;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;

class MicroKernelTraitTest extends TestCase
{
    public function test()
    {
        $kernel = new ConcreteMicroKernel('test', false);
        $kernel->boot();

        $request = Request::create('/');
        $response = $kernel->handle($request);

        $this->assertEquals('halloween', $response->getContent());
        $this->assertEquals('Have a great day!', $kernel->getContainer()->getParameter('halloween'));
        $this->assertInstanceOf('stdClass', $kernel->getContainer()->get('halloween'));
    }

    public function testAsEventSubscriber()
    {
        $kernel = new ConcreteMicroKernel('test', false);
        $kernel->boot();

        $request = Request::create('/danger');
        $response = $kernel->handle($request);

        $this->assertSame('It\'s dangerous to go alone. Take this ⚔', $response->getContent());
    }
}
