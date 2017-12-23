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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Config\EnvParametersResource;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest;
use Symfony\Component\HttpKernel\Tests\Fixtures\KernelForOverrideName;
use Symfony\Component\HttpKernel\Tests\Fixtures\KernelWithoutBundles;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class KernelTest extends TestCase
{
    public static function tearDownAfterClass()
    {
        $fs = new Filesystem();
        $fs->remove(__DIR__.'/Fixtures/cache');
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

    public function testInitializeContainerClearsOldContainers()
    {
        $fs = new Filesystem();
        $legacyContainerDir = __DIR__.'/Fixtures/cache/custom/ContainerA123456';
        $fs->mkdir($legacyContainerDir);
        touch($legacyContainerDir.'.legacy');

        $kernel = new CustomProjectDirKernel();
        $kernel->boot();

        $containerDir = __DIR__.'/Fixtures/cache/custom/'.substr(get_class($kernel->getContainer()), 0, 16);
        $this->assertTrue(unlink(__DIR__.'/Fixtures/cache/custom/FixturesCustomDebugProjectContainer.php.meta'));
        $this->assertFileExists($containerDir);
        $this->assertFileNotExists($containerDir.'.legacy');

        $kernel = new CustomProjectDirKernel(function ($container) { $container->register('foo', 'stdClass')->setPublic(true); });
        $kernel->boot();

        $this->assertFileExists($containerDir);
        $this->assertFileExists($containerDir.'.legacy');

        $this->assertFileNotExists($legacyContainerDir);
        $this->assertFileNotExists($legacyContainerDir.'.legacy');
    }

    public function testBootInitializesBundlesAndContainer()
    {
        $kernel = $this->getKernel(array('initializeBundles', 'initializeContainer'));
        $kernel->expects($this->once())
            ->method('initializeBundles');
        $kernel->expects($this->once())
            ->method('initializeContainer');

        $kernel->boot();
    }

    public function testBootSetsTheContainerToTheBundles()
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\Bundle')->getMock();
        $bundle->expects($this->once())
            ->method('setContainer');

        $kernel = $this->getKernel(array('initializeBundles', 'initializeContainer', 'getBundles'));
        $kernel->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array($bundle)));

        $kernel->boot();
    }

    public function testBootSetsTheBootedFlagToTrue()
    {
        // use test kernel to access isBooted()
        $kernel = $this->getKernelForTest(array('initializeBundles', 'initializeContainer'));
        $kernel->boot();

        $this->assertTrue($kernel->isBooted());
    }

    /**
     * @group legacy
     */
    public function testClassCacheIsLoaded()
    {
        $kernel = $this->getKernel(array('initializeBundles', 'initializeContainer', 'doLoadClassCache'));
        $kernel->loadClassCache('name', '.extension');
        $kernel->expects($this->once())
            ->method('doLoadClassCache')
            ->with('name', '.extension');

        $kernel->boot();
    }

    public function testClassCacheIsNotLoadedByDefault()
    {
        $kernel = $this->getKernel(array('initializeBundles', 'initializeContainer', 'doLoadClassCache'));
        $kernel->expects($this->never())
            ->method('doLoadClassCache');

        $kernel->boot();
    }

    /**
     * @group legacy
     */
    public function testClassCacheIsNotLoadedWhenKernelIsNotBooted()
    {
        $kernel = $this->getKernel(array('initializeBundles', 'initializeContainer', 'doLoadClassCache'));
        $kernel->loadClassCache();
        $kernel->expects($this->never())
            ->method('doLoadClassCache');
    }

    public function testEnvParametersResourceIsAdded()
    {
        $container = new ContainerBuilder();
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->disableOriginalConstructor()
            ->setMethods(array('getContainerBuilder', 'prepareContainer', 'getCacheDir', 'getLogDir'))
            ->getMock();
        $kernel->expects($this->any())
            ->method('getContainerBuilder')
            ->will($this->returnValue($container));
        $kernel->expects($this->any())
            ->method('prepareContainer')
            ->will($this->returnValue(null));
        $kernel->expects($this->any())
            ->method('getCacheDir')
            ->will($this->returnValue(sys_get_temp_dir()));
        $kernel->expects($this->any())
            ->method('getLogDir')
            ->will($this->returnValue(sys_get_temp_dir()));

        $reflection = new \ReflectionClass(get_class($kernel));
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        $method->invoke($kernel);

        $found = false;
        foreach ($container->getResources() as $resource) {
            if ($resource instanceof EnvParametersResource) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found);
    }

    public function testBootKernelSeveralTimesOnlyInitializesBundlesOnce()
    {
        $kernel = $this->getKernel(array('initializeBundles', 'initializeContainer'));
        $kernel->expects($this->once())
            ->method('initializeBundles');

        $kernel->boot();
        $kernel->boot();
    }

    public function testShutdownCallsShutdownOnAllBundles()
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\Bundle')->getMock();
        $bundle->expects($this->once())
            ->method('shutdown');

        $kernel = $this->getKernel(array(), array($bundle));

        $kernel->boot();
        $kernel->shutdown();
    }

    public function testShutdownGivesNullContainerToAllBundles()
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\Bundle')->getMock();
        $bundle->expects($this->at(3))
            ->method('setContainer')
            ->with(null);

        $kernel = $this->getKernel(array('getBundles'));
        $kernel->expects($this->any())
            ->method('getBundles')
            ->will($this->returnValue(array($bundle)));

        $kernel->boot();
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

        $kernel = $this->getKernel(array('getHttpKernel'));
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

        $kernel = $this->getKernel(array('getHttpKernel', 'boot'));
        $kernel->expects($this->once())
            ->method('getHttpKernel')
            ->will($this->returnValue($httpKernelMock));

        $kernel->expects($this->once())
            ->method('boot');

        $kernel->handle($request, $type, $catch);
    }

    public function testStripComments()
    {
        $source = <<<'EOF'
<?php

$string = 'string should not be   modified';

$string = 'string should not be

modified';


$heredoc = <<<HD


Heredoc should not be   modified {$a[1+$b]}


HD;

$nowdoc = <<<'ND'


Nowdoc should not be   modified


ND;

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
        $expected = <<<'EOF'
<?php
$string = 'string should not be   modified';
$string = 'string should not be

modified';
$heredoc = <<<HD


Heredoc should not be   modified {$a[1+$b]}


HD;
$nowdoc = <<<'ND'


Nowdoc should not be   modified


ND;
class TestClass
{
    public function doStuff()
    {
        }
}
EOF;

        $output = Kernel::stripComments($source);

        // Heredocs are preserved, making the output mixing Unix and Windows line
        // endings, switching to "\n" everywhere on Windows to avoid failure.
        if ('\\' === DIRECTORY_SEPARATOR) {
            $expected = str_replace("\r\n", "\n", $expected);
            $output = str_replace("\r\n", "\n", $output);
        }

        $this->assertEquals($expected, $output);
    }

    public function testGetRootDir()
    {
        $kernel = new KernelForTest('test', true);

        $this->assertEquals(__DIR__.DIRECTORY_SEPARATOR.'Fixtures', realpath($kernel->getRootDir()));
    }

    public function testGetName()
    {
        $kernel = new KernelForTest('test', true);

        $this->assertEquals('Fixtures', $kernel->getName());
    }

    public function testOverrideGetName()
    {
        $kernel = new KernelForOverrideName('test', true);

        $this->assertEquals('overridden', $kernel->getName());
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
        $this->getKernel()->locateResource('Foo');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testLocateResourceThrowsExceptionWhenNameIsUnsafe()
    {
        $this->getKernel()->locateResource('@FooBundle/../bar');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLocateResourceThrowsExceptionWhenBundleDoesNotExist()
    {
        $this->getKernel()->locateResource('@FooBundle/config/routing.xml');
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testLocateResourceThrowsExceptionWhenResourceDoesNotExist()
    {
        $kernel = $this->getKernel(array('getBundle'));
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle'))))
        ;

        $kernel->locateResource('@Bundle1Bundle/config/routing.xml');
    }

    public function testLocateResourceReturnsTheFirstThatMatches()
    {
        $kernel = $this->getKernel(array('getBundle'));
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle'))))
        ;

        $this->assertEquals(__DIR__.'/Fixtures/Bundle1Bundle/foo.txt', $kernel->locateResource('@Bundle1Bundle/foo.txt'));
    }

    /**
     * @group legacy
     */
    public function testLocateResourceReturnsTheFirstThatMatchesWithParent()
    {
        $parent = $this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle');
        $child = $this->getBundle(__DIR__.'/Fixtures/Bundle2Bundle');

        $kernel = $this->getKernel(array('getBundle'));
        $kernel
            ->expects($this->exactly(2))
            ->method('getBundle')
            ->will($this->returnValue(array($child, $parent)))
        ;

        $this->assertEquals(__DIR__.'/Fixtures/Bundle2Bundle/foo.txt', $kernel->locateResource('@ParentAABundle/foo.txt'));
        $this->assertEquals(__DIR__.'/Fixtures/Bundle1Bundle/bar.txt', $kernel->locateResource('@ParentAABundle/bar.txt'));
    }

    /**
     * @group legacy
     */
    public function testLocateResourceReturnsAllMatches()
    {
        $parent = $this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle');
        $child = $this->getBundle(__DIR__.'/Fixtures/Bundle2Bundle');

        $kernel = $this->getKernel(array('getBundle'));
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($child, $parent)))
        ;

        $this->assertEquals(array(
            __DIR__.'/Fixtures/Bundle2Bundle/foo.txt',
            __DIR__.'/Fixtures/Bundle1Bundle/foo.txt', ),
            $kernel->locateResource('@Bundle1Bundle/foo.txt', null, false));
    }

    /**
     * @group legacy
     */
    public function testLocateResourceReturnsAllMatchesBis()
    {
        $kernel = $this->getKernel(array('getBundle'));
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array(
                $this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle'),
                $this->getBundle(__DIR__.'/Foobar'),
            )))
        ;

        $this->assertEquals(
            array(__DIR__.'/Fixtures/Bundle1Bundle/foo.txt'),
            $kernel->locateResource('@Bundle1Bundle/foo.txt', null, false)
        );
    }

    public function testLocateResourceIgnoresDirOnNonResource()
    {
        $kernel = $this->getKernel(array('getBundle'));
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
        $kernel = $this->getKernel(array('getBundle'));
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

    /**
     * @group legacy
     */
    public function testLocateResourceReturnsTheDirOneForResourcesAndBundleOnes()
    {
        $kernel = $this->getKernel(array('getBundle'));
        $kernel
            ->expects($this->once())
            ->method('getBundle')
            ->will($this->returnValue(array($this->getBundle(__DIR__.'/Fixtures/Bundle1Bundle', null, null, 'Bundle1Bundle'))))
        ;

        $this->assertEquals(array(
            __DIR__.'/Fixtures/Resources/Bundle1Bundle/foo.txt',
            __DIR__.'/Fixtures/Bundle1Bundle/Resources/foo.txt', ),
            $kernel->locateResource('@Bundle1Bundle/Resources/foo.txt', __DIR__.'/Fixtures/Resources', false)
        );
    }

    /**
     * @group legacy
     */
    public function testLocateResourceOverrideBundleAndResourcesFolders()
    {
        $parent = $this->getBundle(__DIR__.'/Fixtures/BaseBundle', null, 'BaseBundle', 'BaseBundle');
        $child = $this->getBundle(__DIR__.'/Fixtures/ChildBundle', 'ParentBundle', 'ChildBundle', 'ChildBundle');

        $kernel = $this->getKernel(array('getBundle'));
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
        $kernel = $this->getKernel(array('getBundle'));
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

        $kernel = $this->getKernel(array('getBundle'));
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

    /**
     * @group legacy
     */
    public function testInitializeBundles()
    {
        $parent = $this->getBundle(null, null, 'ParentABundle');
        $child = $this->getBundle(null, 'ParentABundle', 'ChildABundle');

        // use test kernel so we can access getBundleMap()
        $kernel = $this->getKernelForTest(array('registerBundles'));
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($parent, $child)))
        ;
        $kernel->boot();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent), $map['ParentABundle']);
    }

    /**
     * @group legacy
     */
    public function testInitializeBundlesSupportInheritanceCascade()
    {
        $grandparent = $this->getBundle(null, null, 'GrandParentBBundle');
        $parent = $this->getBundle(null, 'GrandParentBBundle', 'ParentBBundle');
        $child = $this->getBundle(null, 'ParentBBundle', 'ChildBBundle');

        // use test kernel so we can access getBundleMap()
        $kernel = $this->getKernelForTest(array('registerBundles'));
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($grandparent, $parent, $child)))
        ;
        $kernel->boot();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent, $grandparent), $map['GrandParentBBundle']);
        $this->assertEquals(array($child, $parent), $map['ParentBBundle']);
        $this->assertEquals(array($child), $map['ChildBBundle']);
    }

    /**
     * @group legacy
     * @expectedException \LogicException
     * @expectedExceptionMessage Bundle "ChildCBundle" extends bundle "FooBar", which is not registered.
     */
    public function testInitializeBundlesThrowsExceptionWhenAParentDoesNotExists()
    {
        $child = $this->getBundle(null, 'FooBar', 'ChildCBundle');
        $kernel = $this->getKernel(array(), array($child));
        $kernel->boot();
    }

    /**
     * @group legacy
     */
    public function testInitializeBundlesSupportsArbitraryBundleRegistrationOrder()
    {
        $grandparent = $this->getBundle(null, null, 'GrandParentCBundle');
        $parent = $this->getBundle(null, 'GrandParentCBundle', 'ParentCBundle');
        $child = $this->getBundle(null, 'ParentCBundle', 'ChildCBundle');

        // use test kernel so we can access getBundleMap()
        $kernel = $this->getKernelForTest(array('registerBundles'));
        $kernel
            ->expects($this->once())
            ->method('registerBundles')
            ->will($this->returnValue(array($parent, $grandparent, $child)))
        ;
        $kernel->boot();

        $map = $kernel->getBundleMap();
        $this->assertEquals(array($child, $parent, $grandparent), $map['GrandParentCBundle']);
        $this->assertEquals(array($child, $parent), $map['ParentCBundle']);
        $this->assertEquals(array($child), $map['ChildCBundle']);
    }

    /**
     * @group legacy
     * @expectedException \LogicException
     * @expectedExceptionMessage Bundle "ParentCBundle" is directly extended by two bundles "ChildC2Bundle" and "ChildC1Bundle".
     */
    public function testInitializeBundlesThrowsExceptionWhenABundleIsDirectlyExtendedByTwoBundles()
    {
        $parent = $this->getBundle(null, null, 'ParentCBundle');
        $child1 = $this->getBundle(null, 'ParentCBundle', 'ChildC1Bundle');
        $child2 = $this->getBundle(null, 'ParentCBundle', 'ChildC2Bundle');

        $kernel = $this->getKernel(array(), array($parent, $child1, $child2));
        $kernel->boot();
    }

    /**
     * @group legacy
     * @expectedException \LogicException
     * @expectedExceptionMessage Trying to register two bundles with the same name "DuplicateName"
     */
    public function testInitializeBundleThrowsExceptionWhenRegisteringTwoBundlesWithTheSameName()
    {
        $fooBundle = $this->getBundle(null, null, 'FooBundle', 'DuplicateName');
        $barBundle = $this->getBundle(null, null, 'BarBundle', 'DuplicateName');

        $kernel = $this->getKernel(array(), array($fooBundle, $barBundle));
        $kernel->boot();
    }

    /**
     * @group legacy
     * @expectedException \LogicException
     * @expectedExceptionMessage Bundle "CircularRefBundle" can not extend itself.
     */
    public function testInitializeBundleThrowsExceptionWhenABundleExtendsItself()
    {
        $circularRef = $this->getBundle(null, 'CircularRefBundle', 'CircularRefBundle');

        $kernel = $this->getKernel(array(), array($circularRef));
        $kernel->boot();
    }

    public function testTerminateReturnsSilentlyIfKernelIsNotBooted()
    {
        $kernel = $this->getKernel(array('getHttpKernel'));
        $kernel->expects($this->never())
            ->method('getHttpKernel');

        $kernel->terminate(Request::create('/'), new Response());
    }

    public function testTerminateDelegatesTerminationOnlyForTerminableInterface()
    {
        // does not implement TerminableInterface
        $httpKernel = new TestKernel();

        $kernel = $this->getKernel(array('getHttpKernel'));
        $kernel->expects($this->once())
            ->method('getHttpKernel')
            ->willReturn($httpKernel);

        $kernel->boot();
        $kernel->terminate(Request::create('/'), new Response());

        $this->assertFalse($httpKernel->terminateCalled, 'terminate() is never called if the kernel class does not implement TerminableInterface');

        // implements TerminableInterface
        $httpKernelMock = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernel')
            ->disableOriginalConstructor()
            ->setMethods(array('terminate'))
            ->getMock();

        $httpKernelMock
            ->expects($this->once())
            ->method('terminate');

        $kernel = $this->getKernel(array('getHttpKernel'));
        $kernel->expects($this->exactly(2))
            ->method('getHttpKernel')
            ->will($this->returnValue($httpKernelMock));

        $kernel->boot();
        $kernel->terminate(Request::create('/'), new Response());
    }

    public function testKernelWithoutBundles()
    {
        $kernel = new KernelWithoutBundles('test', true);
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->getParameter('test_executed'));
    }

    public function testKernelRootDirNameStartingWithANumber()
    {
        $dir = __DIR__.'/Fixtures/123';
        require_once $dir.'/Kernel123.php';
        $kernel = new \Symfony\Component\HttpKernel\Tests\Fixtures\_123\Kernel123('dev', true);
        $this->assertEquals('_123', $kernel->getName());
    }

    /**
     * @group legacy
     * @expectedDeprecation The Symfony\Component\HttpKernel\Kernel::getEnvParameters() method is deprecated as of 3.3 and will be removed in 4.0. Use the %cenv()%c syntax to get the value of any environment variable from configuration files instead.
     * @expectedDeprecation The support of special environment variables that start with SYMFONY__ (such as "SYMFONY__FOO__BAR") is deprecated as of 3.3 and will be removed in 4.0. Use the %cenv()%c syntax instead to get the value of environment variables in configuration files.
     */
    public function testSymfonyEnvironmentVariables()
    {
        $_SERVER['SYMFONY__FOO__BAR'] = 'baz';

        $kernel = $this->getKernel();
        $method = new \ReflectionMethod($kernel, 'getEnvParameters');
        $method->setAccessible(true);

        $envParameters = $method->invoke($kernel);
        $this->assertSame('baz', $envParameters['foo.bar']);

        unset($_SERVER['SYMFONY__FOO__BAR']);
    }

    public function testProjectDirExtension()
    {
        $kernel = new CustomProjectDirKernel();
        $kernel->boot();

        $this->assertSame('foo', $kernel->getProjectDir());
        $this->assertSame('foo', $kernel->getContainer()->getParameter('kernel.project_dir'));
    }

    public function testKernelReset()
    {
        (new Filesystem())->remove(__DIR__.'/Fixtures/cache');

        $kernel = new CustomProjectDirKernel();
        $kernel->boot();

        $containerClass = get_class($kernel->getContainer());
        $containerFile = (new \ReflectionClass($kernel->getContainer()))->getFileName();
        unlink(__DIR__.'/Fixtures/cache/custom/FixturesCustomDebugProjectContainer.php.meta');

        $kernel = new CustomProjectDirKernel();
        $kernel->boot();

        $this->assertSame($containerClass, get_class($kernel->getContainer()));
        $this->assertFileExists($containerFile);
        unlink(__DIR__.'/Fixtures/cache/custom/FixturesCustomDebugProjectContainer.php.meta');

        $kernel = new CustomProjectDirKernel(function ($container) { $container->register('foo', 'stdClass')->setPublic(true); });
        $kernel->boot();

        $this->assertTrue(get_class($kernel->getContainer()) !== $containerClass);
        $this->assertFileExists($containerFile);
        $this->assertFileExists(dirname($containerFile).'.legacy');
    }

    public function testKernelPass()
    {
        $kernel = new PassKernel();
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->getParameter('test.processed'));
    }

    public function testServicesResetter()
    {
        $httpKernelMock = $this->getMockBuilder(HttpKernelInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $httpKernelMock
            ->expects($this->exactly(2))
            ->method('handle');

        $kernel = new CustomProjectDirKernel(function ($container) {
            $container->addCompilerPass(new ResettableServicePass());
            $container->register('one', ResettableService::class)
                ->setPublic(true)
                ->addTag('kernel.reset', array('method' => 'reset'));
            $container->register('services_resetter', ServicesResetter::class)->setPublic(true);
        }, $httpKernelMock, 'resetting');

        ResettableService::$counter = 0;

        $request = new Request();

        $kernel->handle($request);
        $kernel->getContainer()->get('one');

        $this->assertEquals(0, ResettableService::$counter);
        $this->assertFalse($kernel->getContainer()->initialized('services_resetter'));

        $kernel->handle($request);

        $this->assertEquals(1, ResettableService::$counter);
    }

    /**
     * Returns a mock for the BundleInterface.
     *
     * @return BundleInterface
     */
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

    /**
     * Returns a mock for the abstract kernel.
     *
     * @param array $methods Additional methods to mock (besides the abstract ones)
     * @param array $bundles Bundles to register
     *
     * @return Kernel
     */
    protected function getKernel(array $methods = array(), array $bundles = array())
    {
        $methods[] = 'registerBundles';

        $kernel = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Kernel')
            ->setMethods($methods)
            ->setConstructorArgs(array('test', false))
            ->getMockForAbstractClass()
        ;
        $kernel->expects($this->any())
            ->method('registerBundles')
            ->will($this->returnValue($bundles))
        ;
        $p = new \ReflectionProperty($kernel, 'rootDir');
        $p->setAccessible(true);
        $p->setValue($kernel, __DIR__.'/Fixtures');

        return $kernel;
    }

    protected function getKernelForTest(array $methods = array())
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest')
            ->setConstructorArgs(array('test', false))
            ->setMethods($methods)
            ->getMock();
        $p = new \ReflectionProperty($kernel, 'rootDir');
        $p->setAccessible(true);
        $p->setValue($kernel, __DIR__.'/Fixtures');

        return $kernel;
    }
}

class TestKernel implements HttpKernelInterface
{
    public $terminateCalled = false;

    public function terminate()
    {
        $this->terminateCalled = true;
    }

    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
    }
}

class CustomProjectDirKernel extends Kernel
{
    private $baseDir;
    private $buildContainer;
    private $httpKernel;

    public function __construct(\Closure $buildContainer = null, HttpKernelInterface $httpKernel = null, $name = 'custom')
    {
        parent::__construct($name, true);

        $this->baseDir = 'foo';
        $this->buildContainer = $buildContainer;
        $this->httpKernel = $httpKernel;
    }

    public function registerBundles()
    {
        return array();
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
    }

    public function getProjectDir()
    {
        return $this->baseDir;
    }

    public function getRootDir()
    {
        return __DIR__.'/Fixtures';
    }

    protected function build(ContainerBuilder $container)
    {
        if ($build = $this->buildContainer) {
            $build($container);
        }
    }

    protected function getHttpKernel()
    {
        return $this->httpKernel;
    }
}

class PassKernel extends CustomProjectDirKernel implements CompilerPassInterface
{
    public function __construct()
    {
        parent::__construct();
        Kernel::__construct('pass', true);
    }

    public function process(ContainerBuilder $container)
    {
        $container->setParameter('test.processed', true);
    }
}
