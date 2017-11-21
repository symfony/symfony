<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Composer\Autoload\ClassLoader;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\HttpKernel\Kernel;

class ControllerNameParserTest extends TestCase
{
    protected $loader;

    protected function setUp()
    {
        $this->loader = new ClassLoader();
        $this->loader->add('TestBundle', __DIR__.'/../Fixtures');
        $this->loader->add('TestApplication', __DIR__.'/../Fixtures');
        $this->loader->register();
    }

    protected function tearDown()
    {
        $this->loader->unregister();
        $this->loader = null;
    }

    public function testParse()
    {
        $parser = $this->createParser();

        $this->assertEquals('TestBundle\FooBundle\Controller\DefaultController::indexAction', $parser->parse('FooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\FooBundle\Controller\Sub\DefaultController::indexAction', $parser->parse('FooBundle:Sub\Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\Sensio\Cms\FooBundle\Controller\DefaultController::indexAction', $parser->parse('SensioCmsFooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\FooBundle\Controller\Test\DefaultController::indexAction', $parser->parse('FooBundle:Test\\Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\FooBundle\Controller\Test\DefaultController::indexAction', $parser->parse('FooBundle:Test/Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');

        try {
            $parser->parse('foo:');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        }
    }

    public function testBuild()
    {
        $parser = $this->createParser();

        $this->assertEquals('FoooooBundle:Default:index', $parser->build('TestBundle\FooBundle\Controller\DefaultController::indexAction'), '->parse() converts a class::method string to a short a:b:c notation string');
        $this->assertEquals('FoooooBundle:Sub\Default:index', $parser->build('TestBundle\FooBundle\Controller\Sub\DefaultController::indexAction'), '->parse() converts a class::method string to a short a:b:c notation string');

        try {
            $parser->build('TestBundle\FooBundle\Controller\DefaultController::index');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        }

        try {
            $parser->build('TestBundle\FooBundle\Controller\Default::indexAction');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        }

        try {
            $parser->build('Foo\Controller\DefaultController::indexAction');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException if the controller is not an aController::cAction string');
        }
    }

    /**
     * @dataProvider getMissingControllersTest
     */
    public function testMissingControllers($name)
    {
        $parser = $this->createParser();

        try {
            $parser->parse($name);
            $this->fail('->parse() throws a \InvalidArgumentException if the class is found but does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws a \InvalidArgumentException if the class is found but does not exist');
        }
    }

    public function getMissingControllersTest()
    {
        // a normal bundle
        $bundles = array(
            array('FooBundle:Fake:index'),
        );

        // a bundle with children
        if (Kernel::VERSION_ID < 40000) {
            $bundles[] = array('SensioFooBundle:Fake:index');
        }

        return $bundles;
    }

    /**
     * @dataProvider getInvalidBundleNameTests
     */
    public function testInvalidBundleName($bundleName, $suggestedBundleName)
    {
        $parser = $this->createParser();

        try {
            $parser->parse($bundleName);
            $this->fail('->parse() throws a \InvalidArgumentException if the bundle does not exist');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws a \InvalidArgumentException if the bundle does not exist');

            if (false === $suggestedBundleName) {
                // make sure we don't have a suggestion
                $this->assertNotContains('Did you mean', $e->getMessage());
            } else {
                $this->assertContains(sprintf('Did you mean "%s"', $suggestedBundleName), $e->getMessage());
            }
        }
    }

    public function getInvalidBundleNameTests()
    {
        return array(
            'Alternative will be found using levenshtein' => array('FoodBundle:Default:index', 'FooBundle:Default:index'),
            'Bundle does not exist at all' => array('CrazyBundle:Default:index', false),
        );
    }

    private function createParser()
    {
        $bundles = array(
            'SensioCmsFooBundle' => $this->getBundle('TestBundle\Sensio\Cms\FooBundle', 'SensioCmsFooBundle'),
            'FooBundle' => $this->getBundle('TestBundle\FooBundle', 'FooBundle'),
        );

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\KernelInterface')->getMock();
        $kernel
            ->expects($this->any())
            ->method('getBundle')
            ->will($this->returnCallback(function ($bundle) use ($bundles) {
                if (!isset($bundles[$bundle])) {
                    throw new \InvalidArgumentException(sprintf('Invalid bundle name "%s"', $bundle));
                }

                return $bundles[$bundle];
            }))
        ;

        $bundles = array(
            'SensioCmsFooBundle' => $this->getBundle('TestBundle\Sensio\Cms\FooBundle', 'SensioCmsFooBundle'),
            'FoooooBundle' => $this->getBundle('TestBundle\FooBundle', 'FoooooBundle'),
            'FooBundle' => $this->getBundle('TestBundle\FooBundle', 'FooBundle'),
        );
        $kernel
            ->expects($this->any())
            ->method('getBundles')
            ->will($this->returnValue($bundles))
        ;

        return new ControllerNameParser($kernel);
    }

    private function getBundle($namespace, $name)
    {
        $bundle = $this->getMockBuilder('Symfony\Component\HttpKernel\Bundle\BundleInterface')->getMock();
        $bundle->expects($this->any())->method('getName')->will($this->returnValue($name));
        $bundle->expects($this->any())->method('getNamespace')->will($this->returnValue($namespace));

        return $bundle;
    }
}
