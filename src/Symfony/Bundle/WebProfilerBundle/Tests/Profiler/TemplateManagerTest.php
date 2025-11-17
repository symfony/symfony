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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;
use Twig\Loader\LoaderInterface;

/**
 * @author Artur Wielogórski <wodor@wodor.net>
 */
class TemplateManagerTest extends TestCase
{
    protected Environment $twigEnvironment;
    protected Profiler $profiler;
    protected TemplateManager $templateManager;

    protected function setUp(): void
    {
        $this->profiler = $this->createMock(Profiler::class);
        $twigEnvironment = $this->mockTwigEnvironment();
        $templates = [
            'data_collector.foo' => ['foo', '@Foo/Collector/foo.html.twig'],
            'data_collector.bar' => ['bar', '@Foo/Collector/bar.html.twig'],
            'data_collector.baz' => ['baz', '@Foo/Collector/baz.html.twig'],
        ];

        $this->templateManager = new TemplateManager($this->profiler, $twigEnvironment, $templates);
    }

    public function testGetNameOfInvalidTemplate()
    {
        $this->expectException(NotFoundHttpException::class);
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
            ->willReturnCallback($this->profilerHasCallback(...));

        $profile = new Profile('token');
        $profile->addCollector(new DummyCollector('foo'));
        $profile->addCollector(new DummyCollector('bar'));
        $this->assertEquals('@Foo/Collector/foo.html.twig', $this->templateManager->getName($profile, 'foo'));
    }

    public function profilerHasCallback($panel)
    {
        return match ($panel) {
            'foo',
            'bar' => true,
            default => false,
        };
    }

    public function profileHasCollectorCallback($panel)
    {
        return match ($panel) {
            'foo',
            'baz' => true,
            default => false,
        };
    }

    protected function mockTwigEnvironment()
    {
        $this->twigEnvironment = $this->createMock(Environment::class);

        $loader = $this->createMock(LoaderInterface::class);
        $loader
            ->expects($this->any())
            ->method('exists')
            ->willReturn(true);

        $this->twigEnvironment->expects($this->any())->method('getLoader')->willReturn($loader);

        return $this->twigEnvironment;
    }
}

class DummyCollector extends DataCollector
{
    public function __construct(private string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }
}
