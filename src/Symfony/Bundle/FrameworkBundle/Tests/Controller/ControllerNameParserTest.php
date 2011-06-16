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

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\ClassLoader\UniversalClassLoader;

class ControllerNameParserTest extends TestCase
{
    protected $loader;

    public function setUp()
    {
        $this->loader = new UniversalClassLoader();
        $this->loader->registerNamespaces(array(
            'TestBundle'      => __DIR__.'/../Fixtures',
            'TestApplication' => __DIR__.'/../Fixtures',
        ));
        $this->loader->register();
    }

    public function tearDown()
    {
        spl_autoload_unregister(array($this->loader, 'loadClass'));

        $this->loader = null;
    }

    public function testParse()
    {
        $parser = $this->createParser();

        $this->assertEquals('TestBundle\FooBundle\Controller\DefaultController::indexAction', $parser->parse('FooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\FooBundle\Controller\Sub\DefaultController::indexAction', $parser->parse('FooBundle:Sub\Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\Fabpot\FooBundle\Controller\DefaultController::indexAction', $parser->parse('SensioFooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');
        $this->assertEquals('TestBundle\Sensio\Cms\FooBundle\Controller\DefaultController::indexAction', $parser->parse('SensioCmsFooBundle:Default:index'), '->parse() converts a short a:b:c notation string to a class::method string');

        try {
            $parser->parse('foo:');
            $this->fail('->parse() throws an \InvalidArgumentException if the controller is not an a:b:c string');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws an \InvalidArgumentException if the controller is not an a:b:c string');
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
            $this->fail('->parse() throws a \InvalidArgumentException if the string is in the valid format, but not matching class can be found');
        } catch (\Exception $e) {
            $this->assertInstanceOf('\InvalidArgumentException', $e, '->parse() throws a \InvalidArgumentException if the class is found but does not exist');
        }
    }

    public function getMissingControllersTest()
    {
        return array(
            array('FooBundle:Fake:index'),          // a normal bundle
            array('SensioFooBundle:Fake:index'),    // a bundle with children
        );
    }

    private function createParser()
    {
        $bundles = array(
            'SensioFooBundle' => array($this->getBundle('TestBundle\Fabpot\FooBundle', 'FabpotFooBundle'), $this->getBundle('TestBundle\Sensio\FooBundle', 'SensioFooBundle')),
            'SensioCmsFooBundle' => array($this->getBundle('TestBundle\Sensio\Cms\FooBundle', 'SensioCmsFooBundle')),
            'FooBundle' => array($this->getBundle('TestBundle\FooBundle', 'FooBundle')),
            'FabpotFooBundle' => array($this->getBundle('TestBundle\Fabpot\FooBundle', 'FabpotFooBundle'), $this->getBundle('TestBundle\Sensio\FooBundle', 'SensioFooBundle')),
        );

        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $kernel
            ->expects($this->any())
            ->method('getBundle')
            ->will($this->returnCallback(function ($bundle) use ($bundles) {
                return $bundles[$bundle];
            }))
        ;

        return new ControllerNameParser($kernel);
    }

    private function getBundle($namespace, $name)
    {
        $bundle = $this->getMock('Symfony\Component\HttpKernel\Bundle\BundleInterface');
        $bundle->expects($this->any())->method('getName')->will($this->returnValue($name));
        $bundle->expects($this->any())->method('getNamespace')->will($this->returnValue($namespace));

        return $bundle;
    }
}
