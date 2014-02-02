<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating;

use Symfony\Bundle\FrameworkBundle\Templating\DelegatingEngine;

class DelegatingEngineTest extends \PHPUnit_Framework_TestCase
{
    public function testSupportsRetrievesEngineFromTheContainer()
    {
        $container = $this->getContainerMock(array(
            'engine.first' => $this->getEngineMock('template.php', false),
            'engine.second' => $this->getEngineMock('template.php', true)
        ));

        $delegatingEngine = new DelegatingEngine($container, array('engine.first', 'engine.second'));

        $this->assertTrue($delegatingEngine->supports('template.php'));
    }

    public function testGetExistingEngine()
    {
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', true);
        $container = $this->getContainerMock(array(
            'engine.first' => $firstEngine,
            'engine.second' => $secondEngine
        ));

        $delegatingEngine = new DelegatingEngine($container, array('engine.first', 'engine.second'));

        $this->assertSame($secondEngine, $delegatingEngine->getEngine('template.php', array('foo' => 'bar')));
    }

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No engine is able to work with the template "template.php"
     */
    public function testGetInvalidEngine()
    {
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', false);
        $container = $this->getContainerMock(array(
            'engine.first' => $firstEngine,
            'engine.second' => $secondEngine
        ));

        $delegatingEngine = new DelegatingEngine($container, array('engine.first', 'engine.second'));
        $delegatingEngine->getEngine('template.php', array('foo' => 'bar'));
    }

    public function testRenderResponseWithFrameworkEngine()
    {
        $response = $this->getMock('Symfony\Component\HttpFoundation\Response');
        $engine = $this->getFrameworkEngineMock('template.php', true);
        $engine->expects($this->once())
            ->method('renderResponse')
            ->with('template.php', array('foo' => 'bar'))
            ->will($this->returnValue($response));
        $container = $this->getContainerMock(array('engine' => $engine));

        $delegatingEngine = new DelegatingEngine($container, array('engine'));

        $this->assertSame($response, $delegatingEngine->renderResponse('template.php', array('foo' => 'bar')));
    }

    public function testRenderResponseWithTemplatingEngine()
    {
        $engine = $this->getEngineMock('template.php', true);
        $container = $this->getContainerMock(array('engine' => $engine));
        $delegatingEngine = new DelegatingEngine($container, array('engine'));

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $delegatingEngine->renderResponse('template.php', array('foo' => 'bar')));
    }

    private function getEngineMock($template, $supports)
    {
        $engine = $this->getMock('Symfony\Component\Templating\EngineInterface');

        $engine->expects($this->once())
            ->method('supports')
            ->with($template)
            ->will($this->returnValue($supports));

        return $engine;
    }

    private function getFrameworkEngineMock($template, $supports)
    {
        $engine = $this->getMock('Symfony\Bundle\FrameworkBundle\Templating\EngineInterface');

        $engine->expects($this->once())
            ->method('supports')
            ->with($template)
            ->will($this->returnValue($supports));

        return $engine;
    }

    private function getContainerMock($services)
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $i = 0;
        foreach ($services as $id => $service) {
            $container->expects($this->at($i++))
                ->method('get')
                ->with($id)
                ->will($this->returnValue($service));
        }

        return $container;
    }
}
