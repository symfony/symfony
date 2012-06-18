<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest;
use Symfony\Component\HttpKernel\Tests\Fixtures\FooBarBundle;

class KernelTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\DependencyInjection\Container')) {
            $this->markTestSkipped('The "DependencyInjection" component is not available');
        }
    }

    public function testConstructor()
    {
        $env = 'test_env';
        $debug = true;
        $kernel = new KernelForTest($env, $debug);

        $this->assertEquals($env, $kernel->getEnvironment());
        $this->assertEquals($debug, $kernel->isDebug());
        $this->assertFalse($kernel->isBooted());
        $this->assertLessThanOrEqual(microtime(true), $kernel->getStartTime());
        $this->assertNull($kernel->getContainer());
    }

    public function testClone()
    {
        $env = 'test_env';
        $debug = true;
        $kernel = new KernelForTest($env, $debug);

        $clone = clone $kernel;

        $this->assertEquals($env, $clone->getEnvironment());
        $this->assertEquals($debug, $clone->isDebug());
        $this->assertFalse($clone->isBooted());
        $this->assertLessThanOrEqual(microtime(true), $clone->getStartTime());
        $this->assertNull($clone->getContainer());
    }

    public function testBootInitializesBundlesAndContainer()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('initializeBundles', 'initializeContainer', 'getBundles'))
            ->getMock();
        $kernel->expects($this->once())
            ->method('initializeBundles');
        $kernel->expects($this->once())
            ->method('initializeContainer');
        $kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array()));

        $kernel->boot();
    }

    public function testBootSetsTheContainerToTheBundles()
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\Bundle')
            ->disableOriginalConstructor()
            ->getMock();
        $bundle->expects($this->once())
            ->method('setContainer');

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('initializeBundles', 'initializeContainer', 'getBundles'))
            ->getMock();
        $kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array($bundle)));

        $kernel->boot();
    }

    public function testBootSetsTheBootedFlagToTrue()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('initializeBundles', 'initializeContainer', 'getBundles'))
            ->getMock();
        $kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array()));

        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    public function testBootKernelSeveralTimesOnlyInitializesBundlesOnce()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('initializeBundles', 'initializeContainer', 'getBundles'))
            ->getMock();
        $kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array()));

        $kernel->boot();
        $kernel->boot();
    }

    public function testShutdownCallsShutdownOnAllBundles()
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\Bundle')
            ->disableOriginalConstructor()
            ->getMock();
        $bundle->expects($this->once())
            ->method('shutdown');

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getBundles'))
            ->getMock();
        $kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array($bundle)));

        $kernel->shutdown();
    }

    public function testShutdownGivesNullContainerToAllBundles()
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\Bundle')
            ->disableOriginalConstructor()
            ->getMock();
        $bundle->expects($this->once())
            ->method('setContainer')
            ->with(null);

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getBundles'))
            ->getMock();
        $kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array($bundle)));

        $kernel->shutdown();
    }

    public function testHandleCallsHandleOnHttpKernel()
    {
        $type = HttpKernelInterface::MASTER_REQUEST;
        $catch = true;
        $request = new Request();

        $httpKernelMock = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernel')
            ->disableOriginalConstructor()
            ->getMock();
        $httpKernelMock
            ->expects($this->once())
            ->method('handle')
            ->with($request, $type, $catch);

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getHttpKernel'))
            ->getMock();

        $kernel->expects($this->once())
            ->method('getHttpKernel')
            ->will($this->returnValue($httpKernelMock));

        $kernel->handle($request, $type, $catch);
    }

    public function testHandleBootsTheKernel()
    {
        $type = HttpKernelInterface::MASTER_REQUEST;
        $catch = true;
        $request = new Request();

        $httpKernelMock = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernel')
            ->disableOriginalConstructor()
            ->getMock();

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getHttpKernel', 'boot'))
            ->getMock();

        $kernel->expects($this->once())
            ->method('getHttpKernel')
            ->will($this->returnValue($httpKernelMock));

        $kernel->expects($this->once())
            ->method('boot');

        // required as this value is initialized
        // in the kernel constructor, which we don't call
        $kernel->setIsBooted(false);

        $kernel->handle($request, $type, $catch);
    }

    public function testStripComments()
    {
        if (!function_exists('token_get_all')) {
            $this->markTestSkipped('The function token_get_all() is not available.');

            return;
        }
        $source = <<<EOF
<?php

/**
 * some class comments to strip
 */
class TestClass
{
    /**
     * some method comments to strip
     */
    public function doStuff()
    {
        // inline comment
    }
}
EOF;
        $expected = <<<EOF
<?php
class TestClass
{
    public function doStuff()
    {
            }
}
EOF;

        $this->assertEquals($expected, Kernel::stripComments($source));
    }

    public function testIsClassInActiveBundleFalse()
    {
        $kernel = $this->getKernelMockForIsClassInActiveBundleTest();

        $this->assertFalse($kernel->isClassInActiveBundle('Not\In\Active\Bundle'));
    }

    public function testIsClassInActiveBundleFalseNoNamespace()
    {
        $kernel = $this->getKernelMockForIsClassInActiveBundleTest();

        $this->assertFalse($kernel->isClassInActiveBundle('NotNamespacedClass'));
    }

    public function testIsClassInActiveBundleTrue()
    {
        $kernel = $this->getKernelMockForIsClassInActiveBundleTest();

        $this->assertTrue($kernel->isClassInActiveBundle(__NAMESPACE__.'\Fixtures\FooBarBundle\SomeClass'));
    }

    protected function getKernelMockForIsClassInActiveBundleTest()
    {
        $bundle = new FooBarBundle();

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getBundles'))
            ->getMock();
        $kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array($bundle)));

        return $kernel;
    }

    public function testGetRootDir()
    {
        $kernel = new KernelForTest('test', true);

        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'Fixtures', $kernel->getRootDir());
    }

    public function testGetName()
    {
        $kernel = new KernelForTest('test', true);

        $this->assertEquals('Fixtures', $kernel->getName());
    }

    public function testSerialize()
    {
        $env = 'test_env';
        $debug = true;
        $kernel = new KernelForTest($env, $debug);

        $expected = serialize(array($env, $debug));
        $this->assertEquals($expected, $kernel->serialize());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLocateResourceThrowsExceptionWhenNameIsNotValid()
    {
        $this->getKernelForInvalidLocateResource()->locateResource('Foo');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLocateResourceThrowsExceptionWhenNameIsUnsafe()
    {
        $this->getKernelForInvalidLocateResource()->locateResource('@FooBundle/../bar');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLocateResourceThrowsExceptionWhenBundleDoesNotExist()
    {
        $this->getKernelForInvalidLocateResource()->locateResource('@FooBundle/config/routing.xml');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLocateResourceThrowsExceptionWhenResourceDoesNotExist()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle'))))
        ;

        $kernel->locateResource('@Bundle1Bundle/config/routing.xml');
    }

    public function testLocateResourceReturnsTheFirstThatMatches()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle'))))
        ;

        $this->assertEquals(__DIR__.'/Fixtures/Bundle1Bundle/foo.txt', $kernel->locateResource('@Bundle1Bundle/foo.txt'));
    }

    public function testLocateResourceReturnsTheFirstThatMatchesWithParent()
    {
        $parent = $this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle');
        $child = $this->getBundle(__DIR__.'/Fixtures/Bundle2Bundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->exactly(2))
            ->method('getBundle')
            ->will($this->returnValue(array($child, $parent)))
        ;

        $this->assertEquals(__DIR__.'/Fixtures/Bundle2Bundle/foo.txt', $kernel->locateResource('@ParentAABundle/foo.txt'));
        $this->assertEquals(__DIR__.'/Fixtures/Bundle1Bundle/bar.txt', $kernel->locateResource('@ParentAABundle/bar.txt'));
    }

    public function testLocateResourceReturnsAllMatches()
    {
        $parent = $this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle');
        $child = $this->getBundle(__DIR__.'/Fixtures/Bundle2Bundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($child, $parent)))
        ;

        $this->assertEquals(array(
            __DIR__.'/Fixtures/Bundle2Bundle/foo.txt',
            __DIR__.'/Fixtures/Bundle1Bundle/foo.txt'),
            $kernel->locateResource('@Bundle1Bundle/foo.txt', null, false));
    }

    public function testLocateResourceReturnsAllMatchesBis()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array(
                $this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle'),
                $this->getBundle(__DIR__.'/Foobar')
            )))
        ;

        $this->assertEquals(
            array(__DIR__.'/Fixtures/Bundle1Bundle/foo.txt'),
            $kernel->locateResource('@Bundle1Bundle/foo.txt', null, false)
        );
    }

    public function testLocateResourceIgnoresDirOnNonResource()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle'))))
        ;

        $this->assertEquals(
            __DIR__.'/Fixtures/Bundle1Bundle/foo.txt',
            $kernel->locateResource('@Bundle1Bundle/foo.txt', __DIR__.'/Fixtures')
        );
    }

    public function testLocateResourceReturnsTheDirOneForResources()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/FooBundle', null, null, 'FooBundle'))))
        ;

        $this->assertEquals(
            __DIR__.'/Fixtures/Resources/FooBundle/foo.txt',
            $kernel->locateResource('@FooBundle/Resources/foo.txt', __DIR__.'/Fixtures/Resources')
        );
    }

    public function testLocateResourceReturnsTheDirOneForResourcesAndBundleOnes()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle', null, null, 'Bundle1Bundle'))))
        ;

        $this->assertEquals(array(
            __DIR__.'/Fixtures/Resources/Bundle1Bundle/foo.txt',
            __DIR__.'/Fixtures/Bundle1Bundle/Resources/foo.txt'),
            $kernel->locateResource('@Bundle1Bundle/Resources/foo.txt', __DIR__.'/Fixtures/Resources', false)
        );
    }

    public function testLocateResourceOverrideBundleAndResourcesFolders()
    {
        $parent = $this->getBundle(__DIR__.'/Fixtures/BaseBundle', null, 'BaseBundle', 'BaseBundle');
        $child = $this->getBundle(__DIR__.'/Fixtures/ChildBundle', 'ParentBundle', 'ChildBundle', 'ChildBundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->exactly(4))
            ->method('getBundle')
            ->will($this->returnValue(array($child, $parent)))
        ;

        $this->assertEquals(array(
            __DIR__.'/Fixtures/Resources/ChildBundle/foo.txt',
            __DIR__.'/Fixtures/ChildBundle/Resources/foo.txt',
            __DIR__.'/Fixtures/BaseBundle/Resources/foo.txt',
            ),
            $kernel->locateResource('@BaseBundle/Resources/foo.txt', __DIR__.'/Fixtures/Resources', false)
        );

        $this->assertEquals(
            __DIR__.'/Fixtures/Resources/ChildBundle/foo.txt',
            $kernel->locateResource('@BaseBundle/Resources/foo.txt', __DIR__.'/Fixtures/Resources')
        );

        try {
            $kernel->locateResource('@BaseBundle/Resources/hide.txt', __DIR__.'/Fixtures/Resources', false);
            $this->fail('Hidden resources should raise an exception when returning an array of matching paths');
        } catch (\RuntimeException $e) {
        }

        try {
            $kernel->locateResource('@BaseBundle/Resources/hide.txt', __DIR__.'/Fixtures/Resources', true);
            $this->fail('Hidden resources should raise an exception when returning the first matching path');
        } catch (\RuntimeException $e) {
        }
    }

    public function testLocateResourceOnDirectories()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->exactly(2))
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/FooBundle', null, null, 'FooBundle'))))
        ;

        $this->assertEquals(
            __DIR__.'/Fixtures/Resources/FooBundle/',
            $kernel->locateResource('@FooBundle/Resources/', __DIR__.'/Fixtures/Resources')
        );
        $this->assertEquals(
            __DIR__.'/Fixtures/Resources/FooBundle',
            $kernel->locateResource('@FooBundle/Resources', __DIR__.'/Fixtures/Resources')
        );

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->exactly(2))
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle', null, null, 'Bundle1Bundle'))))
        ;

        $this->assertEquals(
            __DIR__.'/Fixtures/Bundle1Bundle/Resources/',
            $kernel->locateResource('@Bundle1Bundle/Resources/')
        );
        $this->assertEquals(
            __DIR__.'/Fixtures/Bundle1Bundle/Resources',
            $kernel->locateResource('@Bundle1Bundle/Resources')
        );
    }

    public function testInitializeBundles()
    {
        $parent = $this->getBundle(null, null, 'ParentABundle');
        $child = $this->getBundle(null, 'ParentABundle', 'ChildABundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($parent, $child)))
        ;
        $kernel->initializeBundles();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent), $map['ParentABundle']);
    }

    public function testInitializeBundlesSupportInheritanceCascade()
    {
        $grandparent = $this->getBundle(null, null, 'GrandParentBBundle');
        $parent = $this->getBundle(null, 'GrandParentBBundle', 'ParentBBundle');
        $child = $this->getBundle(null, 'ParentBBundle', 'ChildBBundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($grandparent, $parent, $child)))
        ;

        $kernel->initializeBundles();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent, $grandparent), $map['GrandParentBBundle']);
        $this->assertEquals(array($child, $parent), $map['ParentBBundle']);
        $this->assertEquals(array($child), $map['ChildBBundle']);
    }

    /**
     * @expectedException \LogicException
     */
    public function testInitializeBundlesThrowsExceptionWhenAParentDoesNotExists()
    {
        $child = $this->getBundle(null, 'FooBar', 'ChildCBundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($child)))
        ;
        $kernel->initializeBundles();
    }

    public function testInitializeBundlesSupportsArbitraryBundleRegistrationOrder()
    {
        $grandparent = $this->getBundle(null, null, 'GrandParentCCundle');
        $parent = $this->getBundle(null, 'GrandParentCCundle', 'ParentCCundle');
        $child = $this->getBundle(null, 'ParentCCundle', 'ChildCCundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($parent, $grandparent, $child)))
        ;

        $kernel->initializeBundles();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent, $grandparent), $map['GrandParentCCundle']);
        $this->assertEquals(array($child, $parent), $map['ParentCCundle']);
        $this->assertEquals(array($child), $map['ChildCCundle']);
    }

    /**
     * @expectedException \LogicException
     */
    public function testInitializeBundlesThrowsExceptionWhenABundleIsDirectlyExtendedByTwoBundles()
    {
        $parent = $this->getBundle(null, null, 'ParentCBundle');
        $child1 = $this->getBundle(null, 'ParentCBundle', 'ChildC1Bundle');
        $child2 = $this->getBundle(null, 'ParentCBundle', 'ChildC2Bundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($parent, $child1, $child2)))
        ;
        $kernel->initializeBundles();
    }

    /**
     * @expectedException \LogicException
     */
    public function testInitializeBundleThrowsExceptionWhenRegisteringTwoBundlesWithTheSameName()
    {
        $fooBundle = $this->getBundle(null, null, 'FooBundle', 'DuplicateName');
        $barBundle = $this->getBundle(null, null, 'BarBundle', 'DuplicateName');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($fooBundle, $barBundle)))
        ;
        $kernel->initializeBundles();
    }

    /**
     * @expectedException \LogicException
     */
    public function testInitializeBundleThrowsExceptionWhenABundleExtendsItself()
    {
        $circularRef = $this->getBundle(null, 'CircularRefBundle', 'CircularRefBundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($circularRef)))
        ;
        $kernel->initializeBundles();
    }

    public function testTerminateReturnsSilentlyIfKernelIsNotBooted()
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getHttpKernel'))
            ->getMock();

        $kernel->expects($this->never())
            ->method('getHttpKernel');

        $kernel->setIsBooted(false);
        $kernel->terminate(Request::create('/'), new Response());
    }

    public function testTerminateDelegatesTerminationOnlyForTerminableInterface()
    {
        // does not implement TerminableInterface
        $httpKernelMock = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $httpKernelMock
            ->expects($this->never())
            ->method('terminate');

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getHttpKernel'))
            ->getMock();

        $kernel->expects($this->once())
            ->method('getHttpKernel')
            ->will($this->returnValue($httpKernelMock));

        $kernel->setIsBooted(true);
        $kernel->terminate(Request::create('/'), new Response());

        // implements TerminableInterface
        $httpKernelMock = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernel')
            ->disableOriginalConstructor()
            ->setMethods(array('terminate'))
            ->getMock();

        $httpKernelMock
            ->expects($this->once())
            ->method('terminate');

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getHttpKernel'))
            ->getMock();

        $kernel->expects($this->exactly(2))
            ->method('getHttpKernel')
            ->will($this->returnValue($httpKernelMock));

        $kernel->setIsBooted(true);
        $kernel->terminate(Request::create('/'), new Response());
    }

    protected function getBundle($dir = null, $parent = null, $className = null, $bundleName = null)
    {
        $bundle = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Bundle\BundleInterface')
            ->setMethods(array('getPath', 'getParent', 'getName'))
            ->disableOriginalConstructor()
        ;

        if ($className) {
            $bundle->setMockClassName($className);
        }

        $bundle = $bundle->getMockForAbstractClass();

        $bundle
            ->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(null === $bundleName ? get_class($bundle) : $bundleName))
        ;

        $bundle
            ->expects($this->any())
            ->method('getPath')
            ->will($this->returnValue($dir))
        ;

        $bundle
            ->expects($this->any())
            ->method('getParent')
            ->will($this->returnValue($parent))
        ;

        return $bundle;
    }

    protected function getKernel()
    {
        return $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->setMethods(array('getBundle', 'registerBundles'))
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    protected function getKernelForInvalidLocateResource()
    {
        return $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Kernel')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass()
        ;
    }
}
