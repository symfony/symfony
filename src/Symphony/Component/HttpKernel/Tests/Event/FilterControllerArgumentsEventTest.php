<?php

namespace Symphony\Component\HttpKernel\Tests\Event;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Event\FilterControllerArgumentsEvent;
use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpKernel\Tests\TestHttpKernel;

class FilterControllerArgumentsEventTest extends TestCase
{
    public function testFilterControllerArgumentsEvent()
    {
        $filterController = new FilterControllerArgumentsEvent(new TestHttpKernel(), function () {}, array('test'), new Request(), 1);
        $this->assertEquals($filterController->getArguments(), array('test'));
    }
}
