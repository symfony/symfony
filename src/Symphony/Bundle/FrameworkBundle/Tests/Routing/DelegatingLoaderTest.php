<?php

namespace Symphony\Bundle\FrameworkBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symphony\Bundle\FrameworkBundle\Routing\DelegatingLoader;
use Symphony\Component\Config\Loader\LoaderResolver;

class DelegatingLoaderTest extends TestCase
{
    public function testConstructorApi()
    {
        $controllerNameParser = $this->getMockBuilder(ControllerNameParser::class)
            ->disableOriginalConstructor()
            ->getMock();
        new DelegatingLoader($controllerNameParser, new LoaderResolver());
        $this->assertTrue(true, '__construct() takes a ControllerNameParser and LoaderResolverInterface respectively as its first and second argument.');
    }
}
