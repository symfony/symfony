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

use Symfony\Component\HttpFoundation\Request;

class MicroKernelTraitTest extends \PHPUnit_Framework_TestCase
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
}
