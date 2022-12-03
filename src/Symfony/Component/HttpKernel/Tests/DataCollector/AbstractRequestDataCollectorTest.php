<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;

abstract class AbstractRequestDataCollectorTest extends TestCase
{
    public function inheritedControllerMethod($name, $callable, $expected)
    {
        $c = new RequestDataCollector();
        $request = $this->createRequest();
        $response = $this->createResponse();
        $this->injectController($c, $callable, $request);
        $c->collect($request, $response);
        $c->lateCollect();

        $this->assertSame($expected, $c->getController()->getValue(true), sprintf('Testing: %s', $name));
    }
}
