<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class MicroKernelTraitTest extends TestCase
{
    public function test()
    {
        $kernel = new ConcreteMicroKernel('test', true);
        $kernel->boot();

        $request = Request::create('/');
        $response = $kernel->handle($request);

        $this->assertEquals('halloween', $response->getContent());
        $this->assertEquals('Have a great day!', $kernel->getContainer()->getParameter('halloween'));
        $this->assertInstanceOf('stdClass', $kernel->getContainer()->get('halloween'));
    }

    public function testAsEventSubscriber()
    {
        $kernel = new ConcreteMicroKernel('test', true);
        $kernel->boot();

        $request = Request::create('/danger');
        $response = $kernel->handle($request);

        $this->assertSame('It\'s dangerous to go alone. Take this ⚔', $response->getContent());
    }
}
