<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Profiler;

use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;
use Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager;

/**
 * Test for TemplateManager class.
 *
 * @author Artur Wielogórski <wodor@wodor.net>
 */
class TemplateManagerTest extends TestCase
{
    /**
     * @var \Twig_Environment
     */
    protected $twigEnvironment;

    /**
     * @var \Symfony\Component\HttpKernel\Profiler\Profiler
     */
    protected $profiler;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $profile;

    /**
     * @var \Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager
     */
    protected $templateManager;

    public function setUp()
    {
        parent::setUp();

        $profiler = $this->mockProfiler();
        $twigEnvironment = $this->mockTwigEnvironment();
        $templates = array(
            'data_collector.foo' => array('foo','FooBundle:Collector:foo'),
            'data_collector.bar' => array('bar','FooBundle:Collector:bar'),
            'data_collector.baz' => array('baz','FooBundle:Collector:baz'),
            );

        $this->templateManager = new TemplateManager($profiler, $twigEnvironment, $templates);
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
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

    /**
     * template should be loaded for 'foo' because other collectors are
     * missing in profile or in profiler.
     */
    public function testGetTemplates()
    {
        $profile = $this->mockProfile();
        $profile->expects($this->any())
            ->method('hasCollector')
            ->will($this->returnCallback(array($this, 'profilerHasCallback')));

        $this->profiler->expects($this->any())
            ->method('has')
            ->withAnyParameters()
            ->will($this->returnCallback(array($this, 'profileHasCollectorCallback')));

        $result = $this->templateManager->getTemplates($profile);
        $this->assertArrayHasKey('foo', $result);
        $this->assertArrayNotHasKey('bar', $result);
        $this->assertArrayNotHasKey('baz', $result);
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
        $this->profile = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profile')
            ->disableOriginalConstructor()
            ->getMock();

        return  $this->profile;
    }

    protected function mockTwigEnvironment()
    {
        $this->twigEnvironment = $this->getMockBuilder('Twig_Environment')->getMock();

        $this->twigEnvironment->expects($this->any())
            ->method('loadTemplate')
            ->will($this->returnValue('loadedTemplate'));

        $this->twigEnvironment->expects($this->any())
            ->method('getLoader')
            ->will($this->returnValue($this->getMock('\Twig_LoaderInterface')));

        return $this->twigEnvironment;
    }

    protected function mockProfiler()
    {
        $this->profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        return $this->profiler;
    }
}
