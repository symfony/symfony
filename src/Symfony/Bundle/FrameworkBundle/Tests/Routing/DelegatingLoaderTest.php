<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;

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
