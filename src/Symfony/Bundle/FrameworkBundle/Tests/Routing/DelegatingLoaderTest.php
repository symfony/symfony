<?php

namespace Symfony\Bundle\FrameworkBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;

class DelegatingLoaderTest extends TestCase
{
    /** @var ControllerNameParser */
    private $controllerNameParser;

    protected function setUp()
    {
        $this->controllerNameParser = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser')
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @group legacy
     */
    public function testLegacyConstructorApi()
    {
        new DelegatingLoader($this->controllerNameParser, new NullLogger(), new LoaderResolver());
        $this->assertTrue(true, '__construct() accepts a LoggerInterface instance as its second argument');
    }

    /**
     * @group legacy
     */
    public function testLegacyConstructorApiAcceptsNullAsSecondArgument()
    {
        new DelegatingLoader($this->controllerNameParser, null, new LoaderResolver());
        $this->assertTrue(true, '__construct() accepts null as its second argument');
    }

    public function testConstructorApi()
    {
        new DelegatingLoader($this->controllerNameParser, new LoaderResolver());
        $this->assertTrue(true, '__construct() takes a ControllerNameParser and LoaderResolverInterface respectively as its first and second argument.');
    }
}
