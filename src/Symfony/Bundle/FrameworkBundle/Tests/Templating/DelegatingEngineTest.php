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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Templating\DelegatingEngine;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Response;

class DelegatingEngineTest extends TestCase
{
    public function testSupportsRetrievesEngineFromTheContainer()
    {
        $container = $this->getContainerMock([
            'engine.first' => $this->getEngineMock('template.php', false),
            'engine.second' => $this->getEngineMock('template.php', true),
        ]);

        $delegatingEngine = new DelegatingEngine($container, ['engine.first', 'engine.second']);

        $this->assertTrue($delegatingEngine->supports('template.php'));
    }

    public function testGetExistingEngine()
    {
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', true);
        $container = $this->getContainerMock([
            'engine.first' => $firstEngine,
            'engine.second' => $secondEngine,
        ]);

        $delegatingEngine = new DelegatingEngine($container, ['engine.first', 'engine.second']);

        $this->assertSame($secondEngine, $delegatingEngine->getEngine('template.php'));
    }

    public function testGetInvalidEngine()
    {
        $this->expectException('RuntimeException');
        $this->expectExceptionMessage('No engine is able to work with the template "template.php"');
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', false);
        $container = $this->getContainerMock([
            'engine.first' => $firstEngine,
            'engine.second' => $secondEngine,
        ]);

        $delegatingEngine = new DelegatingEngine($container, ['engine.first', 'engine.second']);
        $delegatingEngine->getEngine('template.php');
    }

    public function testRenderResponseWithFrameworkEngine()
    {
        $response = new Response();
        $engine = $this->getFrameworkEngineMock('template.php', true);
        $engine->expects($this->once())
            ->method('renderResponse')
            ->with('template.php', ['foo' => 'bar'])
            ->willReturn($response);
        $container = $this->getContainerMock(['engine' => $engine]);

        $delegatingEngine = new DelegatingEngine($container, ['engine']);

        $this->assertSame($response, $delegatingEngine->renderResponse('template.php', ['foo' => 'bar']));
    }

    public function testRenderResponseWithTemplatingEngine()
    {
        $engine = $this->getEngineMock('template.php', true);
        $container = $this->getContainerMock(['engine' => $engine]);
        $delegatingEngine = new DelegatingEngine($container, ['engine']);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $delegatingEngine->renderResponse('template.php', ['foo' => 'bar']));
    }

    private function getEngineMock($template, $supports)
    {
        $engine = $this->getMockBuilder('Symfony\Component\Templating\EngineInterface')->getMock();

        $engine->expects($this->once())
            ->method('supports')
            ->with($template)
            ->willReturn($supports);

        return $engine;
    }

    private function getFrameworkEngineMock($template, $supports)
    {
        $engine = $this->getMockBuilder('Symfony\Bundle\FrameworkBundle\Templating\EngineInterface')->getMock();

        $engine->expects($this->once())
            ->method('supports')
            ->with($template)
            ->willReturn($supports);

        return $engine;
    }

    private function getContainerMock($services)
    {
        $container = new ContainerBuilder();

        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        return $container;
    }
}
