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

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLocateResourceThrowsExceptionWhenNameIsNotValid()
    {
        $this->getKernelForInvalidLocateResource()->locateResource('foo');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLocateResourceThrowsExceptionWhenNameIsUnsafe()
    {
        $this->getKernelForInvalidLocateResource()->locateResource('@foo/../bar');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLocateResourceThrowsExceptionWhenBundleDoesNotExist()
    {
        $this->getKernelForInvalidLocateResource()->locateResource('@foo/config/routing.xml');
    }

    public function testLocateResourceReturnsTheFirstThatMatches()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1'))))
        ;

        $this->assertEquals(__DIR__.'/Fixtures/Bundle1/foo.txt', $kernel->locateResource('@foo/foo.txt'));
    }

    public function testLocateResourceReturnsTheFirstThatMatchesWithParent()
    {
        $parent = $this->getBundle(__DIR__.'/Fixtures/Bundle1', null, 'ParentAA');
        $child = $this->getBundle(__DIR__.'/Fixtures/Bundle2', 'ParentAA', 'ChildAA');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->any())
            ->method('getBundle')
            ->will($this->returnValue(array($child, $parent)))
        ;

        $this->assertEquals(__DIR__.'/Fixtures/Bundle2/foo.txt', $kernel->locateResource('@foo/foo.txt'));
        $this->assertEquals(__DIR__.'/Fixtures/Bundle1/bar.txt', $kernel->locateResource('@foo/bar.txt'));
    }

    public function testLocateResourceReturnsTheAllMatches()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1'), $this->getBundle(__DIR__.'/Fixtures/Bundle2'))))
        ;

        $this->assertEquals(array(__DIR__.'/Fixtures/Bundle1/foo.txt', __DIR__.'/Fixtures/Bundle2/foo.txt'), $kernel->locateResource('@foo/foo.txt', null, false));
    }

    public function testLocateResourceReturnsAllMatchesBis()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1'), $this->getBundle(__DIR__.'/foobar'))))
        ;

        $this->assertEquals(array(__DIR__.'/Fixtures/Bundle1/foo.txt'), $kernel->locateResource('@foo/foo.txt', null, false));
    }

    public function testLocateResourceIgnoresDirOnNonResource()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1'))))
        ;

        $this->assertEquals(__DIR__.'/Fixtures/Bundle1/foo.txt', $kernel->locateResource('@foo/foo.txt', __DIR__.'/Fixtures'));
    }

    public function testLocateResourceReturnsTheDirOneForResources()
    {
        $kernel = $this->getKernel();

        $this->assertEquals(__DIR__.'/Fixtures/fooBundle/foo.txt', $kernel->locateResource('@foo/Resources/foo.txt', __DIR__.'/Fixtures'));
    }

    public function testLocateResourceReturnsTheDirOneForResourcesAndBundleOnes()
    {
        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1'))))
        ;

        $this->assertEquals(array(__DIR__.'/Fixtures/fooBundle/foo.txt', __DIR__.'/Fixtures/Bundle1/Resources/foo.txt'), $kernel->locateResource('@foo/Resources/foo.txt', __DIR__.'/Fixtures', false));
    }

    public function testLocateResourceOnDirectories()
    {
        $kernel = $this->getKernel();

        $this->assertEquals(__DIR__.'/Fixtures/fooBundle/', $kernel->locateResource('@foo/Resources/', __DIR__.'/Fixtures'));
        $this->assertEquals(__DIR__.'/Fixtures/fooBundle/', $kernel->locateResource('@foo/Resources', __DIR__.'/Fixtures'));

        $kernel
            ->expects($this->any())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1'))))
        ;

        $this->assertEquals(__DIR__.'/Fixtures/Bundle1/Resources/', $kernel->locateResource('@foo/Resources/'));
        $this->assertEquals(__DIR__.'/Fixtures/Bundle1/Resources/', $kernel->locateResource('@foo/Resources/'));
    }

    public function testInitializeBundles()
    {
        $parent = $this->getBundle(null, null, 'ParentABundle');
        $child = $this->getBundle(null, 'ParentA', 'ChildABundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($parent, $child)))
        ;
        $kernel->initializeBundles();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent), $map['ParentA']);
    }

    public function testInitializeBundlesSupportInheritanceCascade()
    {
        $grandparent = $this->getBundle(null, null, 'GrandParentBBundle');
        $parent = $this->getBundle(null, 'GrandParentB', 'ParentBBundle');
        $child = $this->getBundle(null, 'ParentB', 'ChildBBundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($grandparent, $parent, $child)))
        ;

        $kernel->initializeBundles();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent, $grandparent), $map['GrandParentB']);
        $this->assertEquals(array($child, $parent), $map['ParentB']);
        $this->assertEquals(array($child), $map['ChildB']);
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
        $grandparent = $this->getBundle(null, null, 'GrandParentCBundle');
        $parent = $this->getBundle(null, 'GrandParentC', 'ParentCBundle');
        $child = $this->getBundle(null, 'ParentC', 'ChildC1Bundle');

        $kernel = $this->getKernel();
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($parent, $grandparent, $child)))
        ;

        $kernel->initializeBundles();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent, $grandparent), $map['GrandParentC']);
        $this->assertEquals(array($child, $parent), $map['ParentC']);
        $this->assertEquals(array($child), $map['ChildC1']);
    }

    /**
     * @expectedException \LogicException
     */
    public function testInitializeBundlesThrowsExceptionWhenABundleIsDirectlyExtendedByTwoBundles()
    {
        $parent = $this->getBundle(null, null, 'ParentC1Bundle');
        $child1 = $this->getBundle(null, 'ParentC1', 'ChildC2Bundle');
        $child2 = $this->getBundle(null, 'ParentC1', 'ChildC3Bundle');

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
        if (null === $className) {
            $className = 'Test'.rand(11111, 99999).'Bundle';
        }

        if (null === $bundleName) {
            $bundleName = substr($className, 0, -6);
        }

        $bundle = $this->getMockBuilder('Symfony\Tests\Component\HttpKernel\BundleForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getPath', 'getParent', 'getName'))
            ->setMockClassName($className)
            ->getMockForAbstractClass();

        $bundle->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($bundleName));

        $bundle->expects($this->any())
            ->method('getPath')
            ->will($this->returnValue(strtr($dir, '\\', '/')));

        $bundle->expects($this->any())
            ->method('getParent')
            ->will($this->returnValue($parent));

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

    protected function getKernelForInvalidLocateResource()
    {
        return $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Kernel')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass()
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
