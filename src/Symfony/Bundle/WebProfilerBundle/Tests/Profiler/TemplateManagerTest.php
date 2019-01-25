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

use Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;
use Symfony\Component\HttpKernel\Profiler\Profile;
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
     * @var \Symfony\Component\HttpKernel\Profiler\Profiler
     */
    protected $profiler;

    /**
     * @var \Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager
     */
    protected $templateManager;

    protected function setUp()
    {
        parent::setUp();

        $profiler = $this->mockProfiler();
        $twigEnvironment = $this->mockTwigEnvironment();
        $templates = [
            'data_collector.foo' => ['foo', 'FooBundle:Collector:foo'],
            'data_collector.bar' => ['bar', 'FooBundle:Collector:bar'],
            'data_collector.baz' => ['baz', 'FooBundle:Collector:baz'],
        ];

        $this->templateManager = new TemplateManager($profiler, $twigEnvironment, $templates);
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function testGetNameOfInvalidTemplate()
    {
        $this->templateManager->getName(new Profile('token'), 'notexistingpanel');
    }

    /**
     * if template exists in both profile and profiler then its name should be returned.
     */
    public function testGetNameValidTemplate()
    {
        $this->profiler->expects($this->any())
            ->method('has')
            ->withAnyParameters()
            ->will($this->returnCallback([$this, 'profilerHasCallback']));

        $this->assertEquals('FooBundle:Collector:foo.html.twig', $this->templateManager->getName(new ProfileDummy(), 'foo'));
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
        return $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profile')->disableOriginalConstructor()->getMock();
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
        $this->profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        return $this->profiler;
    }
}

class ProfileDummy extends Profile
{
    public function __construct()
    {
        parent::__construct('token');
    }

    public function hasCollector($name)
    {
        switch ($name) {
            case 'foo':
            case 'bar':
                return true;
            default:
                return false;
        }
    }
}
