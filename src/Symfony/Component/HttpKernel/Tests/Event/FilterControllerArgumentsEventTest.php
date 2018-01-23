<?php

namespace Symfony\Component\HttpKernel\Tests\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class FilterControllerArgumentsEventTest extends TestCase
{
    public function testFilterControllerArgumentsEvent()
    {
        $filterController = new FilterControllerArgumentsEvent(new TestHttpKernel(), function () {}, array('test'), new Request(), 1);
        $this->assertEquals($filterController->getArguments(), array('test'));
    }
}
