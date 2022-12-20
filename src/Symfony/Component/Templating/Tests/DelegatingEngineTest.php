<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Templating\DelegatingEngine;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Templating\StreamingEngineInterface;

class DelegatingEngineTest extends TestCase
{
    public function testRenderDelegatesToSupportedEngine()
    {
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', true);

        $secondEngine->expects(self::once())
            ->method('render')
            ->with('template.php', ['foo' => 'bar'])
            ->willReturn('<html />');

        $delegatingEngine = new DelegatingEngine([$firstEngine, $secondEngine]);
        $result = $delegatingEngine->render('template.php', ['foo' => 'bar']);

        self::assertSame('<html />', $result);
    }

    public function testRenderWithNoSupportedEngine()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('No engine is able to work with the template "template.php"');
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', false);

        $delegatingEngine = new DelegatingEngine([$firstEngine, $secondEngine]);
        $delegatingEngine->render('template.php', ['foo' => 'bar']);
    }

    public function testStreamDelegatesToSupportedEngine()
    {
        $streamingEngine = $this->getStreamingEngineMock('template.php', true);
        $streamingEngine->expects(self::once())
            ->method('stream')
            ->with('template.php', ['foo' => 'bar'])
            ->willReturn('<html />');

        $delegatingEngine = new DelegatingEngine([$streamingEngine]);
        $result = $delegatingEngine->stream('template.php', ['foo' => 'bar']);

        self::assertNull($result);
    }

    public function testStreamRequiresStreamingEngine()
    {
        self::expectException(\LogicException::class);
        self::expectExceptionMessage('Template "template.php" cannot be streamed as the engine supporting it does not implement StreamingEngineInterface');
        $delegatingEngine = new DelegatingEngine([new TestEngine()]);
        $delegatingEngine->stream('template.php', ['foo' => 'bar']);
    }

    public function testExists()
    {
        $engine = $this->getEngineMock('template.php', true);
        $engine->expects(self::once())
            ->method('exists')
            ->with('template.php')
            ->willReturn(true);

        $delegatingEngine = new DelegatingEngine([$engine]);

        self::assertTrue($delegatingEngine->exists('template.php'));
    }

    public function testSupports()
    {
        $engine = $this->getEngineMock('template.php', true);

        $delegatingEngine = new DelegatingEngine([$engine]);

        self::assertTrue($delegatingEngine->supports('template.php'));
    }

    public function testSupportsWithNoSupportedEngine()
    {
        $engine = $this->getEngineMock('template.php', false);

        $delegatingEngine = new DelegatingEngine([$engine]);

        self::assertFalse($delegatingEngine->supports('template.php'));
    }

    public function testGetExistingEngine()
    {
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', true);

        $delegatingEngine = new DelegatingEngine([$firstEngine, $secondEngine]);

        self::assertSame($secondEngine, $delegatingEngine->getEngine('template.php'));
    }

    public function testGetInvalidEngine()
    {
        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('No engine is able to work with the template "template.php"');
        $firstEngine = $this->getEngineMock('template.php', false);
        $secondEngine = $this->getEngineMock('template.php', false);

        $delegatingEngine = new DelegatingEngine([$firstEngine, $secondEngine]);
        $delegatingEngine->getEngine('template.php');
    }

    private function getEngineMock($template, $supports)
    {
        $engine = self::createMock(EngineInterface::class);

        $engine->expects(self::once())
            ->method('supports')
            ->with($template)
            ->willReturn($supports);

        return $engine;
    }

    private function getStreamingEngineMock($template, $supports)
    {
        $engine = self::getMockForAbstractClass(MyStreamingEngine::class);

        $engine->expects(self::once())
            ->method('supports')
            ->with($template)
            ->willReturn($supports);

        return $engine;
    }
}

interface MyStreamingEngine extends StreamingEngineInterface, EngineInterface
{
}

class TestEngine implements EngineInterface
{
    public function render($name, array $parameters = []): string
    {
    }

    public function exists($name): bool
    {
    }

    public function supports($name): bool
    {
        return true;
    }

    public function stream()
    {
    }
}
