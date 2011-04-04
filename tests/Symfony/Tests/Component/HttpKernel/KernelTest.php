<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel;

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Config\Loader\LoaderInterface;

class KernelTest extends \PHPUnit_Framework_TestCase
{
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
        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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

        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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
        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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
        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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

        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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

        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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

        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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

        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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
            $this->markTestSkipped();
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

        $this->assertTrue($kernel->isClassInActiveBundle(__NAMESPACE__.'\FooBarBundle\SomeClass'));
    }

    protected function getKernelMockForIsClassInActiveBundleTest()
    {
        $bundle = new FooBarBundle();

        $kernel = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
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

        $this->assertEquals(__DIR__, $kernel->getRootDir());
    }

    public function testGetName()
    {
        $kernel = new KernelForTest('test', true);

        $this->assertEquals('HttpKernel', $kernel->getName());
    }

    public function testSerialize()
    {
        $env = 'test_env';
        $debug = true;
        $kernel = new KernelForTest($env, $debug);

        $expected = serialize(array($env, $debug));
        $this->assertEquals($expected, $kernel->serialize());
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

    protected function getBundle($dir = null, $parent = null, $className = null, $bundleName = null)
    {
        $bundle = $this
            ->getMockBuilder('Symfony\Tests\Component\HttpKernel\BundleForTest')
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
            ->will($this->returnValue(is_null($bundleName) ? get_class($bundle) : $bundleName))
        ;

        $bundle
            ->expects($this->any())
            ->method('getPath')
            ->will($this->returnValue(strtr($dir, '\\', '/')))
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
            ->getMockBuilder('Symfony\Tests\Component\HttpKernel\KernelForTest')
            ->setMethods(array('getBundle', 'registerBundles'))
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }
}

class KernelForTest extends Kernel
{
    public function getBundleMap()
    {
        return $this->bundleMap;
    }

    public function registerBundles()
    {
    }

    public function registerBundleDirs()
    {
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }

    public function initializeBundles()
    {
        parent::initializeBundles();
    }

    public function isBooted()
    {
        return $this->booted;
    }

    public function setIsBooted($value)
    {
        $this->booted = (bool) $value;
    }
}

abstract class BundleForTest implements BundleInterface
{
    // We can not extend Symfony\Component\HttpKernel\Bundle\Bundle as we want to mock getName() which is final
}

class FooBarBundle extends Bundle
{
    // We need a full namespaced bundle instance to test isClassInActiveBundle
}
