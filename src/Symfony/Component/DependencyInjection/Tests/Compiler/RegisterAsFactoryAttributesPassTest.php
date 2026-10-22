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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AsFactory;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\RegisterAsFactoryAttributesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

class RegisterAsFactoryAttributesPassTest extends TestCase
{
    public function testFactoryServicesAreAutowirableAfterCompilation()
    {
        $container = $this->createContainer();
        $container->register('consumer', AsFactoryConsumer::class)
            ->setAutowired(true)
            ->setPublic(true);

        $container->compile();

        $product = $container->get('consumer')->product;

        $this->assertSame('factory:configured', $product->name);
        $this->assertInstanceOf(AsFactoryDependency::class, $product->dependency);
        $this->assertFalse($container->has(AsFactoryProduct::class));
    }

    public function testReturnTypeIsTheDefaultServiceId()
    {
        $container = $this->createContainer();

        (new RegisterAsFactoryAttributesPass())->process($container);

        $definition = $container->getDefinition(AsFactoryProduct::class);

        $this->assertSame(AsFactoryProduct::class, $definition->getClass());
        $this->assertEquals([new Reference('factory'), 'create'], $definition->getFactory());
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->isAutoconfigured());
        $this->assertTrue($definition->isShared());
        $this->assertFalse($definition->isPublic());
    }

    public function testExplicitIdAndStaticFactory()
    {
        $container = $this->createContainer();

        (new RegisterAsFactoryAttributesPass())->process($container);

        $definition = $container->getDefinition('named.product');

        $this->assertSame(\ArrayObject::class, $definition->getClass());
        $this->assertSame([AsFactoryServices::class, 'createNamed'], $definition->getFactory());
        $this->assertFalse($container->hasDefinition(\ArrayObject::class));
    }

    public function testExistingDefinitionKeepsItsConfiguration()
    {
        $container = $this->createContainer();
        $definition = $container->register(AsFactoryProduct::class, AsFactoryProduct::class)
            ->setArguments([new Reference(AsFactoryDependency::class), 'custom'])
            ->setPublic(true)
            ->setShared(false)
            ->addTag('existing.tag');

        (new RegisterAsFactoryAttributesPass())->process($container);

        $this->assertSame($definition, $container->getDefinition(AsFactoryProduct::class));
        $this->assertEquals([new Reference('factory'), 'create'], $definition->getFactory());
        $this->assertEquals([new Reference(AsFactoryDependency::class), 'custom'], $definition->getArguments());
        $this->assertTrue($definition->isPublic());
        $this->assertFalse($definition->isShared());
        $this->assertFalse($definition->isAutowired());
        $this->assertFalse($definition->isAutoconfigured());
        $this->assertSame([[]], $definition->getTag('existing.tag'));
    }

    public function testExistingArgumentsAndSharingApplyToTheFactory()
    {
        $container = $this->createContainer();
        $container->register(AsFactoryProduct::class, AsFactoryProduct::class)
            ->setArguments([new Reference(AsFactoryDependency::class), 'custom'])
            ->setPublic(true)
            ->setShared(false);

        $container->compile();

        $this->assertSame('factory:custom', $container->get(AsFactoryProduct::class)->name);
        $this->assertNotSame($container->get(AsFactoryProduct::class), $container->get(AsFactoryProduct::class));
    }

    public function testProducedServicesAreAutoconfigured()
    {
        $container = $this->createContainer();
        $container->registerForAutoconfiguration(AsFactoryProduct::class)
            ->addTag('configured.product');
        $container->setAlias('public.product', AsFactoryProduct::class)->setPublic(true);

        $container->compile();

        $definition = $container->getDefinition('public.product');

        $this->assertSame([[]], $definition->getTag('attributed.product'));
        $this->assertSame([[]], $definition->getTag('configured.product'));
        $this->assertSame('factory:configured', $container->get('public.product')->name);
    }

    public function testAnExistingAliasKeepsTheTargetDefinition()
    {
        $container = $this->createContainer();
        $definition = $container->register('actual.product', \ArrayObject::class)
            ->setPublic(true)
            ->addTag('existing.tag');
        $container->setAlias('named.product', 'actual.product');

        (new RegisterAsFactoryAttributesPass())->process($container);

        $this->assertSame('actual.product', (string) $container->getAlias('named.product'));
        $this->assertSame($definition, $container->getDefinition('actual.product'));
        $this->assertSame([AsFactoryServices::class, 'createNamed'], $definition->getFactory());
        $this->assertSame([[]], $definition->getTag('existing.tag'));
    }

    public function testProducedFactoriesAreDiscovered()
    {
        $container = new ContainerBuilder();
        $container->register('factory', AsFactoryOuter::class)->setAutoconfigured(true);
        $container->setAlias('public.product', \stdClass::class)->setPublic(true);

        $container->compile();

        $this->assertSame('nested', $container->get('public.product')->factory);
    }

    public function testFactoryDefinitionCanInheritItsClass()
    {
        $container = new ContainerBuilder();
        $container->setParameter('factory.name', 'configured');
        $container->register(AsFactoryDependency::class, AsFactoryDependency::class);
        $container->register('factory.parent', AsFactoryServices::class)->setAbstract(true);
        $container->setDefinition('factory', (new ChildDefinition('factory.parent'))->setAutoconfigured(true));
        $container->setAlias('public.product', AsFactoryProduct::class)->setPublic(true);

        $container->compile();

        $this->assertSame('factory:configured', $container->get('public.product')->name);
    }

    public function testExistingProductDefinitionKeepsItsInheritedClass()
    {
        $container = new ContainerBuilder();
        $container->register('factory', AsFactoryInheritedProductFactory::class)->setAutoconfigured(true);
        $container->register('product.parent', AsFactoryInheritedProduct::class)->setAbstract(true);
        $container->setDefinition('inherited.product', (new ChildDefinition('product.parent'))->setAutoconfigured(true));
        $container->setAlias('public.product', 'inherited.product')->setPublic(true);

        $container->compile();

        $definition = $container->getDefinition('public.product');

        $this->assertSame(AsFactoryInheritedProduct::class, $definition->getClass());
        $this->assertSame([[]], $definition->getTag('concrete.product'));
        $this->assertInstanceOf(AsFactoryInheritedProduct::class, $container->get('public.product'));
    }

    public function testDumpedContainerCallsInstanceAndStaticFactories()
    {
        $container = $this->createContainer();
        $container->setAlias('public.product', AsFactoryProduct::class)->setPublic(true);
        $container->setAlias('public.named', 'named.product')->setPublic(true);
        $container->compile();

        $code = (new PhpDumper($container))->dump(['class' => 'AsFactoryAttributesTestContainer']);
        eval('?>'.$code);

        $dumpedContainer = new \AsFactoryAttributesTestContainer();
        $product = $dumpedContainer->get('public.product');

        $this->assertSame('factory:configured', $product->name);
        $this->assertInstanceOf(AsFactoryDependency::class, $product->dependency);
        $this->assertSame($product, $dumpedContainer->get('public.product'));
        $this->assertSame(['configured'], $dumpedContainer->get('public.named')->getArrayCopy());
    }

    #[DataProvider('provideIgnoredDefinitions')]
    public function testIgnoredDefinitionsRegisterNoProducts(bool $autoconfigured, bool $abstract, bool $ignoreAttributes)
    {
        $container = new ContainerBuilder();
        $definition = $container->register('factory', AsFactoryServices::class)
            ->setAutoconfigured($autoconfigured)
            ->setAbstract($abstract);
        if ($ignoreAttributes) {
            $definition->addTag('container.ignore_attributes');
        }

        (new RegisterAsFactoryAttributesPass())->process($container);

        $this->assertFalse($container->hasDefinition(AsFactoryProduct::class));
        $this->assertFalse($container->hasDefinition('named.product'));
    }

    public static function provideIgnoredDefinitions(): iterable
    {
        yield 'autoconfiguration disabled' => [false, false, false];
        yield 'abstract definition' => [true, true, false];
        yield 'attributes ignored' => [true, false, true];
    }

    public function testTwoFactoriesCannotClaimTheSameServiceId()
    {
        $container = $this->createContainer();
        $container->register('other.factory', AsFactoryServices::class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(AsFactoryProduct::class);

        (new RegisterAsFactoryAttributesPass())->process($container);
    }

    public function testTwoFactoriesCannotClaimAliasesOfTheSameDefinition()
    {
        $container = new ContainerBuilder();
        $container->register('factory', AsFactoryAliasedProducts::class)->setAutoconfigured(true);
        $container->register('actual.product', \stdClass::class);
        $container->setAlias('first.product', 'actual.product');
        $container->setAlias('second.product', 'actual.product');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('#[AsFactory]');

        (new RegisterAsFactoryAttributesPass())->process($container);
    }

    #[DataProvider('provideDefinitionOrders')]
    public function testDuplicateFactoriesAreRejectedRegardlessOfDefinitionOrder(bool $factoryFirst)
    {
        $container = new ContainerBuilder();
        foreach ($factoryFirst ? ['factory', AsFactorySelfParent::class] : [AsFactorySelfParent::class, 'factory'] as $id) {
            $container->register($id, AsFactorySelfParent::class)->setAutoconfigured(true);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(AsFactorySelfParent::class);

        (new RegisterAsFactoryAttributesPass())->process($container);
    }

    public static function provideDefinitionOrders(): iterable
    {
        yield 'factory first' => [true];
        yield 'product first' => [false];
    }

    public function testAnExistingFactoryCannotBeOverridden()
    {
        $container = $this->createContainer();
        $container->register(AsFactoryProduct::class, AsFactoryProduct::class)
            ->setFactory([AsFactoryServices::class, 'other']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(AsFactoryProduct::class);

        (new RegisterAsFactoryAttributesPass())->process($container);
    }

    public function testAnInheritedFactoryCannotBeOverridden()
    {
        $container = $this->createContainer();
        $container->register('product.parent', AsFactoryProduct::class)
            ->setAbstract(true)
            ->setFactory([AsFactoryServices::class, 'other']);
        $container->setDefinition(AsFactoryProduct::class, new ChildDefinition('product.parent'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(AsFactoryProduct::class);

        (new RegisterAsFactoryAttributesPass())->process($container);
    }

    #[DataProvider('provideInvalidProductDefinitions')]
    public function testAbstractAndSyntheticProductDefinitionsAreRejected(bool $abstract, bool $synthetic)
    {
        $container = $this->createContainer();
        $container->register(AsFactoryProduct::class, AsFactoryProduct::class)
            ->setAbstract($abstract)
            ->setSynthetic($synthetic);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(AsFactoryProduct::class);

        (new RegisterAsFactoryAttributesPass())->process($container);
    }

    public static function provideInvalidProductDefinitions(): iterable
    {
        yield 'abstract' => [true, false];
        yield 'synthetic' => [false, true];
    }

    public function testNonPublicFactoryIsRejected()
    {
        $container = new ContainerBuilder();
        $container->register('factory', AsFactoryNonPublic::class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-public method "'.AsFactoryNonPublic::class.'::create()"');

        (new RegisterAsFactoryAttributesPass())->process($container);
    }

    #[DataProvider('provideInvalidReturnTypes')]
    public function testInvalidReturnTypeIsRejected(string $class)
    {
        $container = new ContainerBuilder();
        $container->register('factory', $class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($class.'::create()');

        (new RegisterAsFactoryAttributesPass())->process($container);
    }

    public static function provideInvalidReturnTypes(): iterable
    {
        yield 'no return type' => [AsFactoryWithoutReturnType::class];
        yield 'scalar' => [AsFactoryScalarReturn::class];
        yield 'object' => [AsFactoryObjectReturn::class];
        yield 'nullable' => [AsFactoryNullableReturn::class];
        yield 'union' => [AsFactoryUnionReturn::class];
        yield 'intersection' => [AsFactoryIntersectionReturn::class];
        yield 'explicit id without return type' => [AsFactoryNamedWithoutReturnType::class];
    }

    public function testInterfaceReturnType()
    {
        $container = new ContainerBuilder();
        $container->register('factory', AsFactoryInterfaceReturn::class)->setAutoconfigured(true);
        $container->setAlias('public.product', \Countable::class)->setPublic(true);

        $container->compile();

        $this->assertSame(2, $container->get('public.product')->count());
    }

    #[DataProvider('provideRelativeReturnTypes')]
    public function testInheritedRelativeReturnTypes(string $class, string $productClass)
    {
        $container = new ContainerBuilder();
        $container->register('factory', $class)->setAutoconfigured(true);
        $container->setAlias('public.product', $productClass)->setPublic(true);

        $container->compile();

        $this->assertInstanceOf($productClass, $container->get('public.product'));
    }

    public static function provideRelativeReturnTypes(): iterable
    {
        yield 'self' => [AsFactorySelfChild::class, AsFactorySelfParent::class];
        yield 'static' => [AsFactoryStaticChild::class, AsFactoryStaticChild::class];
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('factory.name', 'configured');
        $container->register('factory', AsFactoryServices::class)->setAutoconfigured(true);
        $container->register(AsFactoryDependency::class, AsFactoryDependency::class);

        return $container;
    }
}

class AsFactoryServices
{
    #[AsFactory]
    public function create(AsFactoryDependency $dependency, #[Autowire(param: 'factory.name')] string $name): AsFactoryProduct
    {
        return new AsFactoryProduct('factory:'.$name, $dependency);
    }

    #[AsFactory('named.product')]
    public static function createNamed(#[Autowire(param: 'factory.name')] string $name): \ArrayObject
    {
        return new \ArrayObject([$name]);
    }
}

#[AutoconfigureTag('attributed.product')]
class AsFactoryProduct
{
    public function __construct(public string $name, public AsFactoryDependency $dependency)
    {
    }
}

class AsFactoryDependency
{
}

class AsFactoryConsumer
{
    public function __construct(public AsFactoryProduct $product)
    {
    }
}

class AsFactoryNonPublic
{
    #[AsFactory]
    private function create(): \stdClass
    {
        return new \stdClass();
    }
}

class AsFactoryWithoutReturnType
{
    #[AsFactory]
    public function create()
    {
        return new \stdClass();
    }
}

class AsFactoryScalarReturn
{
    #[AsFactory]
    public function create(): int
    {
        return 1;
    }
}

class AsFactoryObjectReturn
{
    #[AsFactory]
    public function create(): object
    {
        return new \stdClass();
    }
}

class AsFactoryNullableReturn
{
    #[AsFactory]
    public function create(): ?\stdClass
    {
        return null;
    }
}

class AsFactoryUnionReturn
{
    #[AsFactory]
    public function create(): \stdClass|\ArrayObject
    {
        return new \stdClass();
    }
}

class AsFactoryIntersectionReturn
{
    #[AsFactory]
    public function create(): \Countable&\Traversable
    {
        return new \ArrayObject();
    }
}

class AsFactoryNamedWithoutReturnType
{
    #[AsFactory('named.product')]
    public function create()
    {
        return new \stdClass();
    }
}

class AsFactoryInterfaceReturn
{
    #[AsFactory]
    public static function create(): \Countable
    {
        return new \ArrayObject(['first', 'second']);
    }
}

class AsFactorySelfParent
{
    #[AsFactory]
    public static function create(): self
    {
        return new self();
    }
}

class AsFactorySelfChild extends AsFactorySelfParent
{
}

class AsFactoryStaticParent
{
    #[AsFactory]
    public static function create(): static
    {
        return new static();
    }
}

class AsFactoryStaticChild extends AsFactoryStaticParent
{
}

class AsFactoryOuter
{
    #[AsFactory]
    public function create(): AsFactoryNested
    {
        return new AsFactoryNested();
    }
}

class AsFactoryNested
{
    #[AsFactory]
    public function create(): \stdClass
    {
        return (object) ['factory' => 'nested'];
    }
}

class AsFactoryAliasedProducts
{
    #[AsFactory('first.product')]
    public function createFirst(): \stdClass
    {
        return new \stdClass();
    }

    #[AsFactory('second.product')]
    public function createSecond(): \stdClass
    {
        return new \stdClass();
    }
}

#[AutoconfigureTag('concrete.product')]
class AsFactoryInheritedProduct extends \ArrayObject
{
}

class AsFactoryInheritedProductFactory
{
    #[AsFactory('inherited.product')]
    public function create(): \Countable
    {
        return new AsFactoryInheritedProduct();
    }
}
