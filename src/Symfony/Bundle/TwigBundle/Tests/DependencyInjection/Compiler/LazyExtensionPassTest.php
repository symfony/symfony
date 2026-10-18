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
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\LazyExtensionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\AttributeExtension;
use Twig\Extension\GlobalsInterface;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

class LazyExtensionPassTest extends TestCase
{
    public function testExtensionsWithDependenciesAreMadeLazy()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('extension')])
            ->addMethodCall('addExtension', [new Reference('aliased_extension')])
            ->addMethodCall('addExtension', [new Reference('inline_dependency')])
            ->addMethodCall('addExtension', [new Reference('setter_dependency')])
            ->addMethodCall('addGlobal', ['app', new Reference('app')]);
        $container->register('extension', TranslationExtension::class)
            ->addArgument(new Reference('translator'));
        $container->register('other_extension', TranslationExtension::class)
            ->addArgument(new Reference('translator'));
        $container->setAlias('aliased_extension', 'other_extension');
        $container->register('inline_dependency', TranslationExtension::class)
            ->addArgument(new Definition(IdentityTranslator::class));
        $container->register('setter_dependency', ExtensionWithSetter::class)
            ->addMethodCall('setDependency', [new Reference('router')]);
        $container->register('app', \stdClass::class)
            ->addArgument(new Reference('request_stack'));

        (new LazyExtensionPass())->process($container);

        $this->assertTrue($container->getDefinition('extension')->isLazy());
        $this->assertTrue($container->getDefinition('other_extension')->isLazy());
        $this->assertTrue($container->getDefinition('inline_dependency')->isLazy());
        $this->assertTrue($container->getDefinition('setter_dependency')->isLazy());
        $this->assertFalse($container->getDefinition('app')->isLazy());
    }

    public function testExtensionsWithoutDependenciesAreNotMadeLazy()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('no_argument')])
            ->addMethodCall('addExtension', [new Reference('attribute')]);
        $container->register('no_argument', CsrfExtension::class);
        $container->register('attribute', AttributeExtension::class)
            ->addArgument(\stdClass::class);

        (new LazyExtensionPass())->process($container);

        $this->assertFalse($container->getDefinition('no_argument')->isLazy());
        $this->assertFalse($container->getDefinition('attribute')->isLazy());
    }

    public function testExplicitLazinessIsKept()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('extension')]);
        $container->register('extension', TranslationExtension::class)
            ->addArgument(new Reference('translator'))
            ->setLazy(false);

        (new LazyExtensionPass())->process($container);

        $this->assertFalse($container->getDefinition('extension')->isLazy());
    }

    public function testExtensionsThatCannotBeLazyAreSkipped()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('factory')])
            ->addMethodCall('addExtension', [new Reference('internal_parent')])
            ->addMethodCall('addExtension', [new Reference('unknown_class')]);
        $container->register('factory', TranslationExtension::class)
            ->setFactory([new Reference('extension_factory'), 'create'])
            ->addArgument(new Reference('translator'));
        $container->register('internal_parent', ExtensionWithInternalParent::class)
            ->addArgument(new Reference('router'));
        $container->register('unknown_class', 'Foo\Bar')
            ->addArgument(new Reference('router'));

        (new LazyExtensionPass())->process($container);

        $this->assertFalse($container->getDefinition('factory')->isLazy());
        $this->assertFalse($container->getDefinition('internal_parent')->isLazy());
        $this->assertFalse($container->getDefinition('unknown_class')->isLazy());
    }

    public function testExtensionsDeclaringOnlyAttributeCallablesAreRegisteredAsRuntimes()
    {
        if (!self::twigReturnsRuntimeExtensions()) {
            $this->markTestSkipped('This version of Twig does not return the runtime of an extension registered through AttributeExtension.');
        }

        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('extension')]);
        $container->register('extension', RoutingExtension::class)
            ->addArgument(new Reference('router'));

        (new LazyExtensionPass())->process($container);

        $this->assertEquals([['addExtension', [new Definition(AttributeExtension::class, [RoutingExtension::class])]]], $container->getDefinition('twig')->getMethodCalls());
        $this->assertTrue($container->getDefinition('extension')->hasTag('twig.runtime'));
        $this->assertFalse($container->getDefinition('extension')->isLazy());
    }

    public function testExtensionsDeclaringOnlyAttributeCallablesAreMadeLazyOnOlderTwigVersions()
    {
        if (self::twigReturnsRuntimeExtensions()) {
            $this->markTestSkipped('This version of Twig returns the runtime of an extension registered through AttributeExtension.');
        }

        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('extension')]);
        $container->register('extension', RoutingExtension::class)
            ->addArgument(new Reference('router'));

        (new LazyExtensionPass())->process($container);

        $this->assertEquals([['addExtension', [new Reference('extension')]]], $container->getDefinition('twig')->getMethodCalls());
        $this->assertFalse($container->getDefinition('extension')->hasTag('twig.runtime'));
        $this->assertTrue($container->getDefinition('extension')->isLazy());
    }

    public function testExtensionsDeclaringCallablesOnlyWithAttributesAreRegisteredAsRuntimesWithAnyTwigVersion()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('extension')]);
        $container->register('extension', AttributeOnlyExtension::class)
            ->addArgument(new Reference('router'));

        (new LazyExtensionPass())->process($container);

        $this->assertEquals([['addExtension', [new Definition(AttributeExtension::class, [AttributeOnlyExtension::class])]]], $container->getDefinition('twig')->getMethodCalls());
        $this->assertTrue($container->getDefinition('extension')->hasTag('twig.runtime'));
        $this->assertFalse($container->getDefinition('extension')->isLazy());
    }

    public function testExtensionsProvidingMoreThanAttributeCallablesAreMadeLazy()
    {
        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('token_parsers')])
            ->addMethodCall('addExtension', [new Reference('globals')]);
        $container->register('token_parsers', AttributeExtensionWithTokenParsers::class)
            ->addArgument(new Reference('router'));
        $container->register('globals', AttributeExtensionWithGlobals::class)
            ->addArgument(new Reference('router'));

        (new LazyExtensionPass())->process($container);

        $this->assertEquals([['addExtension', [new Reference('token_parsers')]], ['addExtension', [new Reference('globals')]]], $container->getDefinition('twig')->getMethodCalls());
        $this->assertTrue($container->getDefinition('token_parsers')->isLazy());
        $this->assertTrue($container->getDefinition('globals')->isLazy());
    }

    public function testSubclassesOfAttributeExtensionAreRegisteredAsRuntimes()
    {
        if ((new \ReflectionClass(AttributeExtension::class))->isFinal()) {
            $this->markTestSkipped('This version of Twig does not allow extending AttributeExtension.');
        }

        $container = new ContainerBuilder();
        $container->register('twig', Environment::class)
            ->addMethodCall('addExtension', [new Reference('extension')]);
        $container->register('extension', AttributeExtensionSubclass::class)
            ->addArgument(new Reference('router'));

        (new LazyExtensionPass())->process($container);

        $this->assertEquals([['addExtension', [new Definition(AttributeExtension::class, [AttributeExtensionSubclass::class])]]], $container->getDefinition('twig')->getMethodCalls());
        $this->assertTrue($container->getDefinition('extension')->hasTag('twig.runtime'));
        $this->assertFalse($container->getDefinition('extension')->isLazy());
    }

    private static function twigReturnsRuntimeExtensions(): bool
    {
        $extension = new class extends AbstractExtension {};
        $twig = new Environment(new ArrayLoader());
        $twig->addExtension(new AttributeExtension($extension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([$extension::class => static fn () => $extension]));

        return $extension === $twig->getExtension($extension::class);
    }
}

class ExtensionWithInternalParent extends \ArrayObject
{
}

class ExtensionWithSetter extends AbstractExtension
{
    public function setDependency(object $dependency): void
    {
    }
}

class AttributeExtensionWithTokenParsers extends AbstractExtension
{
    public function __construct(object $dependency)
    {
    }

    public function getTokenParsers(): array
    {
        return [];
    }

    #[AsTwigFunction('foo')]
    public function foo(): string
    {
        return 'foo';
    }
}

class AttributeExtensionWithGlobals extends AbstractExtension implements GlobalsInterface
{
    public function __construct(object $dependency)
    {
    }

    public function getGlobals(): array
    {
        return [];
    }

    #[AsTwigFunction('bar')]
    public function bar(): string
    {
        return 'bar';
    }
}

class AttributeOnlyExtension extends AbstractExtension
{
    public function __construct(object $dependency)
    {
    }

    #[AsTwigFunction('baz')]
    public function baz(): string
    {
        return 'baz';
    }
}

if (!(new \ReflectionClass(AttributeExtension::class))->isFinal()) {
    class AttributeExtensionSubclass extends AttributeExtension
    {
        public function __construct(object $dependency)
        {
        }

        #[AsTwigFunction('qux')]
        public function qux(): string
        {
            return 'qux';
        }
    }
}
