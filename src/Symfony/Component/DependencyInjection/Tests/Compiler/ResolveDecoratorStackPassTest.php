<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveDecoratorStackPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ResolveDecoratorStackPassTest extends TestCase
{
    public function testStackDecoratesExistingService()
    {
        $container = new ContainerBuilder();

        // The existing service that should be decorated
        $container->register('original_service', \stdClass::class)
            ->setPublic(true)
            ->setProperty('label', 'original');

        // Define a stack that decorates the existing service
        $stack = (new ChildDefinition(''))
            ->addTag('container.stack')
            ->setArguments([
                (new Definition(\stdClass::class))
                    ->setProperty('label', 'A')
                    ->setProperty('inner', new Reference('.inner')),
                (new Definition(\stdClass::class))
                    ->setProperty('label', 'B')
                    ->setProperty('inner', new Reference('.inner')),
            ])
            ->setDecoratedService('original_service')
        ;

        $container->setDefinition('my_stack', $stack);

        (new ResolveDecoratorStackPass())->process($container);
        (new DecoratorServicePass())->process($container);

        // The original service should now be decorated
        $this->assertTrue($container->hasAlias('original_service'));

        // The innermost definition should wrap the original service
        // and the outermost should be the entry point
        $alias = $container->getAlias('my_stack');
        $outermostId = (string) $alias;

        // The outermost definition should have label 'A'
        $outermostDef = $container->getDefinition($outermostId);
        $this->assertSame('A', $outermostDef->getProperties()['label']);

        // Follow the chain: the original service should still be accessible
        $this->assertTrue($container->hasDefinition('original_service.inner') || $this->hasInnerService($container));
    }

    public function testStackDecoratesWithPriority()
    {
        $container = new ContainerBuilder();

        $container->register('original_service', \stdClass::class)
            ->setPublic(true);

        $stack = (new ChildDefinition(''))
            ->addTag('container.stack')
            ->setArguments([
                new Definition(\stdClass::class),
                new Definition(\stdClass::class),
            ])
            ->setDecoratedService('original_service', null, 5)
        ;

        $container->setDefinition('my_stack', $stack);

        (new ResolveDecoratorStackPass())->process($container);

        // After resolving the stack, the innermost definition should have
        // the decoratedService set to 'original_service' with priority 5
        $resolved = $container->getDefinitions();
        $innermostFound = false;

        foreach ($resolved as $id => $def) {
            if (str_starts_with($id, '.my_stack.') && null !== $def->getDecoratedService()) {
                $decorated = $def->getDecoratedService();
                if ('original_service' === $decorated[0]) {
                    $innermostFound = true;
                    $this->assertSame(5, $decorated[2]);
                }
            }
        }

        $this->assertTrue($innermostFound, 'The innermost stack definition should decorate "original_service"');
    }

    public function testStackDecoratesWithInvalidBehavior()
    {
        $container = new ContainerBuilder();

        $container->register('original_service', \stdClass::class)
            ->setPublic(true);

        $stack = (new ChildDefinition(''))
            ->addTag('container.stack')
            ->setArguments([
                new Definition(\stdClass::class),
                new Definition(\stdClass::class),
            ])
            ->setDecoratedService('original_service', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ;

        $container->setDefinition('my_stack', $stack);

        (new ResolveDecoratorStackPass())->process($container);

        $resolved = $container->getDefinitions();
        $innermostFound = false;

        foreach ($resolved as $id => $def) {
            if (str_starts_with($id, '.my_stack.') && null !== $def->getDecoratedService()) {
                $decorated = $def->getDecoratedService();
                if ('original_service' === $decorated[0]) {
                    $innermostFound = true;
                    $this->assertSame(ContainerInterface::IGNORE_ON_INVALID_REFERENCE, $decorated[3]);
                }
            }
        }

        $this->assertTrue($innermostFound);
    }

    public function testStackWithoutDecoratesStillWorks()
    {
        $container = new ContainerBuilder();

        $stack = (new ChildDefinition(''))
            ->addTag('container.stack')
            ->setArguments([
                (new Definition(\stdClass::class))
                    ->setProperty('label', 'A')
                    ->setProperty('inner', new Reference('.inner')),
                (new Definition(\stdClass::class))
                    ->setProperty('label', 'B'),
            ])
        ;
        $stack->setPublic(true);
        $stack->setChanges(['public' => true]);

        $container->setDefinition('my_stack', $stack);

        (new ResolveDecoratorStackPass())->process($container);

        // The innermost definition should not decorate any external service
        $resolved = $container->getDefinitions();
        $stackDefs = array_filter($resolved, static fn ($v, $id) => str_starts_with($id, '.my_stack.'), \ARRAY_FILTER_USE_BOTH);
        $this->assertCount(2, $stackDefs);

        $innermost = reset($stackDefs);
        $this->assertNull($innermost->getDecoratedService());
    }

    public function testStackDecoratesTag()
    {
        $container = new ContainerBuilder();

        // Two services tagged with 'my_tag'
        $container->register('foo', \stdClass::class)
            ->setPublic(true)
            ->addTag('my_tag')
            ->setProperty('label', 'foo');

        $container->register('bar', \stdClass::class)
            ->setPublic(true)
            ->addTag('my_tag')
            ->setProperty('label', 'bar');

        // A stack that decorates all services tagged 'my_tag'
        $stack = (new ChildDefinition(''))
            ->addTag('container.stack')
            ->addResourceTag('container.tag_decorator', ['decorates_tag' => 'my_tag'])
            ->setArguments([
                (new Definition(\stdClass::class))
                    ->setProperty('label', 'A')
                    ->setProperty('inner', new Reference('.inner')),
                (new Definition(\stdClass::class))
                    ->setProperty('label', 'B')
                    ->setProperty('inner', new Reference('.inner')),
            ])
        ;

        $container->setDefinition('my_stack', $stack);

        (new ResolveDecoratorStackPass())->process($container);
        (new DecoratorServicePass())->process($container);

        // The original stack definition should have been removed
        $this->assertFalse($container->hasDefinition('my_stack'));

        // Both foo and bar should be decorated
        $this->assertTrue($container->hasAlias('foo'));
        $this->assertTrue($container->hasAlias('bar'));
    }

    public function testStackCannotHaveBothDecoratesAndDecoratesTag()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \stdClass::class)
            ->addTag('my_tag');

        $stack = (new ChildDefinition(''))
            ->addTag('container.stack')
            ->addResourceTag('container.tag_decorator', ['decorates_tag' => 'my_tag'])
            ->setDecoratedService('foo')
            ->setArguments([
                new Definition(\stdClass::class),
            ])
        ;

        $container->setDefinition('my_stack', $stack);

        $this->expectException(\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot have both "decorates" and "decorates_tag"');
        (new ResolveDecoratorStackPass())->process($container);
    }

    private function hasInnerService(ContainerBuilder $container): bool
    {
        foreach ($container->getDefinitions() as $id => $def) {
            if (str_ends_with($id, '.inner')) {
                return true;
            }
        }

        return false;
    }
}
