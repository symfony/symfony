<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\WebProfilerBundle\Tests\Profiler;

use Symphony\Bundle\WebProfilerBundle\Tests\TestCase;
use Symphony\Bundle\WebProfilerBundle\Profiler\TemplateManager;
use Twig\Environment;

/**
 * Test for TemplateManager class.
 *
 * @author Artur Wielogórski <wodor@wodor.net>
 */
class TemplateManagerTest extends TestCase
{
    /**
     * @var Environment
     */
    protected $twigEnvironment;

    /**
     * @var \Symphony\Component\HttpKernel\Profiler\Profiler
     */
    protected $profiler;

    /**
     * @var \Symphony\Bundle\WebProfilerBundle\Profiler\TemplateManager
     */
    protected $templateManager;

    protected function setUp()
    {
        parent::setUp();

        $profiler = $this->mockProfiler();
        $twigEnvironment = $this->mockTwigEnvironment();
        $templates = array(
            'data_collector.foo' => array('foo', 'FooBundle:Collector:foo'),
            'data_collector.bar' => array('bar', 'FooBundle:Collector:bar'),
            'data_collector.baz' => array('baz', 'FooBundle:Collector:baz'),
        );

        $this->templateManager = new TemplateManager($profiler, $twigEnvironment, $templates);
    }

    /**
     * @expectedException \Symphony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testGetNameOfInvalidTemplate()
    {
        $profile = $this->mockProfile();
        $this->templateManager->getName($profile, 'notexistingpanel');
    }

    /**
     * if template exists in both profile and profiler then its name should be returned.
     */
    public function testGetNameValidTemplate()
    {
        $this->profiler->expects($this->any())
            ->method('has')
            ->withAnyParameters()
            ->will($this->returnCallback(array($this, 'profilerHasCallback')));

        $profile = $this->mockProfile();
        $profile->expects($this->any())
            ->method('hasCollector')
            ->will($this->returnCallback(array($this, 'profileHasCollectorCallback')));

        $this->assertEquals('FooBundle:Collector:foo.html.twig', $this->templateManager->getName($profile, 'foo'));
    }

    public function profilerHasCallback($panel)
    {
        switch ($panel) {
            case 'foo':
            case 'bar':
                return true;
            default:
                return false;
        }
    }

    public function profileHasCollectorCallback($panel)
    {
        switch ($panel) {
            case 'foo':
            case 'baz':
                return true;
            default:
                return false;
        }
    }

    protected function mockProfile()
    {
        return $this->getMockBuilder('Symphony\Component\HttpKernel\Profiler\Profile')->disableOriginalConstructor()->getMock();
    }

    protected function mockTwigEnvironment()
    {
        $this->twigEnvironment = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();

        $this->twigEnvironment->expects($this->any())
            ->method('loadTemplate')
            ->will($this->returnValue('loadedTemplate'));

        if (interface_exists('Twig\Loader\SourceContextLoaderInterface')) {
            $loader = $this->getMockBuilder('Twig\Loader\SourceContextLoaderInterface')->getMock();
        } else {
            $loader = $this->getMockBuilder('Twig\Loader\LoaderInterface')->getMock();
        }
        $this->twigEnvironment->expects($this->any())->method('getLoader')->will($this->returnValue($loader));

        return $this->twigEnvironment;
    }

    protected function mockProfiler()
    {
        $this->profiler = $this->getMockBuilder('Symphony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        return $this->profiler;
    }
}
