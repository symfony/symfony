<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\SafeClassPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Twig\Runtime\EscaperRuntime;

class SafeClassPassTest extends TestCase
{
    private ContainerBuilder $container;
    private SafeClassPass $pass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->register('twig.runtime.escaper', EscaperRuntime::class);
        $this->pass = new SafeClassPass();
    }

    public function testSafeClassWithStringStrategy()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => 'html']);

        $this->pass->process($this->container);

        $this->assertSame(
            [['addSafeClass', [\stdClass::class, ['html']]]],
            $this->container->getDefinition('twig.runtime.escaper')->getMethodCalls()
        );
    }

    public function testSafeClassWithArrayStrategy()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => ['html', 'js']]);

        $this->pass->process($this->container);

        $this->assertSame(
            [['addSafeClass', [\stdClass::class, ['html', 'js']]]],
            $this->container->getDefinition('twig.runtime.escaper')->getMethodCalls()
        );
    }

    public function testSafeClassWithMultipleTags()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => 'html'])
            ->addResourceTag('twig.safe_class', ['strategy' => 'js']);

        $this->pass->process($this->container);

        $calls = $this->container->getDefinition('twig.runtime.escaper')->getMethodCalls();
        $this->assertContains(['addSafeClass', [\stdClass::class, ['html']]], $calls);
        $this->assertContains(['addSafeClass', [\stdClass::class, ['js']]], $calls);
    }

    public function testSafeClassWithAllSentinel()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => 'all']);

        $this->pass->process($this->container);

        $this->assertSame(
            [['addSafeClass', [\stdClass::class, ['all']]]],
            $this->container->getDefinition('twig.runtime.escaper')->getMethodCalls()
        );
    }

    public function testSafeClassWithCustomStrategy()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => 'markdown']);

        $this->pass->process($this->container);

        $this->assertSame(
            [['addSafeClass', [\stdClass::class, ['markdown']]]],
            $this->container->getDefinition('twig.runtime.escaper')->getMethodCalls()
        );
    }

    public function testThrowsOnMissingStrategy()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "strategy" attribute of the "twig.safe_class" tag on "my_class" must be a string or a list of strings.');

        $this->pass->process($this->container);
    }

    public function testThrowsOnInvalidStrategy()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => 123]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "strategy" attribute of the "twig.safe_class" tag on "my_class" must be a string or a list of strings.');

        $this->pass->process($this->container);
    }

    public function testThrowsOnMixedStrategyArray()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => ['html', 123]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "strategy" attribute of the "twig.safe_class" tag on "my_class" must be a string or a list of strings.');

        $this->pass->process($this->container);
    }

    public function testThrowsOnEmptyStrategyArray()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => []]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "strategy" attribute of the "twig.safe_class" tag on "my_class" must not be empty; use "all" to mark the class safe for every strategy.');

        $this->pass->process($this->container);
    }

    public function testThrowsOnUppercaseStrategy()
    {
        $this->container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => 'JS']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "strategy" attribute of the "twig.safe_class" tag on "my_class" contains the invalid strategy "JS"');

        $this->pass->process($this->container);
    }

    public function testDoesNothingWhenNoEscaperService()
    {
        $container = new ContainerBuilder();
        $container->register('my_class')->setClass(\stdClass::class)
            ->addResourceTag('twig.safe_class', ['strategy' => 'html']);

        (new SafeClassPass())->process($container);

        $this->assertFalse($container->hasDefinition('twig.runtime.escaper'));
    }
}
