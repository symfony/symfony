<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ResettingRequestStack;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ResettingRequestStackTest extends TestCase
{
    protected function setUp()
    {
        ResettableService::$counter = 0;
    }

    public function testResetServicesNoOp()
    {
        $requestStack = new ResettingRequestStack(new \ArrayIterator(array(new ResettableService())), array('reset'));

        $masterRequest = Request::create('/foo');

        $requestStack->push($masterRequest);

        $this->assertEquals(0, ResettableService::$counter);
    }

    public function testResetServices()
    {
        $requestStack = new ResettingRequestStack(new \ArrayIterator(array(new ResettableService())), array('reset'));

        $masterRequest = Request::create('/foo');

        $requestStack->push($masterRequest);
        $this->assertEquals(0, ResettableService::$counter);

        $requestStack->pop();
        $this->assertEquals(0, ResettableService::$counter);

        $requestStack->push($masterRequest);
        $this->assertEquals(1, ResettableService::$counter);

        $requestStack->push($masterRequest);
        $this->assertEquals(1, ResettableService::$counter);
    }
}
