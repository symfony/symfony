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
    /**
     * @var Environment
     */
    protected $twigEnvironment;

    /**
     * @var Profiler
     */
    protected $profiler;

    /**
     * @var TemplateManager
     */
    protected $templateManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profiler = self::createMock(Profiler::class);
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
        self::expectException(NotFoundHttpException::class);
        $this->templateManager->getName(new Profile('token'), 'notexistingpanel');
    }

    /**
     * if template exists in both profile and profiler then its name should be returned.
     */
    public function testGetNameValidTemplate()
    {
        $this->profiler->expects(self::any())
            ->method('has')
            ->withAnyParameters()
            ->willReturnCallback([$this, 'profilerHasCallback']);

        self::assertEquals('@Foo/Collector/foo.html.twig', $this->templateManager->getName(new ProfileDummy(), 'foo'));
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

    protected function mockTwigEnvironment()
    {
        $this->twigEnvironment = self::createMock(Environment::class);

        $loader = self::createMock(LoaderInterface::class);
        $loader
            ->expects(self::any())
            ->method('exists')
            ->willReturn(true);

        $this->twigEnvironment->expects(self::any())->method('getLoader')->willReturn($loader);

        return $this->twigEnvironment;
    }
}

class ProfileDummy extends Profile
{
    public function __construct()
    {
        parent::__construct('token');
    }

    public function hasCollector(string $name): bool
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
