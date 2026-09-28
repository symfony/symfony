<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\DependencyInjection\AttributeMetadataPass;
use Symfony\Component\Serializer\Exception\MappingException;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

class AttributeMetadataPassTest extends TestCase
{
    public function testProcessWithNoAttributeLoader()
    {
        $container = new ContainerBuilder();

        // Should not throw any exception
        (new AttributeMetadataPass())->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testProcessWithAttributeLoaderButNoTaggedServices()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);

        // Should not throw any exception
        (new AttributeMetadataPass())->process($container);

        $arguments = $container->getDefinition('serializer.mapping.attribute_loader')->getArguments();
        $this->assertSame([false, []], $arguments);
    }

    public function testProcessWithTaggedServices()
    {
        $container = new ContainerBuilder();
        $container->setParameter('user_entity.class', 'App\Entity\User');

        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);

        $container->register('service1', '%user_entity.class%')
            ->addTag('serializer.attribute_metadata');
        $container->register('service2', 'App\Entity\Product')
            ->addTag('serializer.attribute_metadata');
        $container->register('service3', 'App\Entity\Order')
            ->addTag('serializer.attribute_metadata');
        // Classes should be deduplicated
        $container->register('service4', 'App\Entity\Order')
            ->addTag('serializer.attribute_metadata');

        (new AttributeMetadataPass())->process($container);

        $arguments = $container->getDefinition('serializer.mapping.attribute_loader')->getArguments();

        // Classes should be sorted alphabetically
        $expectedClasses = [
            'App\Entity\Order' => ['App\Entity\Order'],
            'App\Entity\Product' => ['App\Entity\Product'],
            'App\Entity\User' => ['App\Entity\User'],
        ];
        $this->assertSame([false, $expectedClasses], $arguments);
    }

    public function testProcessWithForOptionAndMatchingMembers()
    {
        $sourceClass = _AttrMeta_Source::class;
        $targetClass = _AttrMeta_Target::class;

        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);

        $container->register('service.source', $sourceClass)
            ->addTag('serializer.attribute_metadata', ['for' => $targetClass]);

        (new AttributeMetadataPass())->process($container);

        $arguments = $container->getDefinition('serializer.mapping.attribute_loader')->getArguments();
        $this->assertSame([false, [$targetClass => [$sourceClass]]], $arguments);
    }

    public function testProcessWithForOptionAndMissingMemberThrows()
    {
        $sourceClass = _AttrMeta_BadSource::class;
        $targetClass = _AttrMeta_Target::class;

        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);

        $container->register('service.source', $sourceClass)
            ->addTag('serializer.attribute_metadata', ['for' => $targetClass]);

        $this->expectException(MappingException::class);
        (new AttributeMetadataPass())->process($container);
    }

    public function testProcessDiscoversAttributesOnNonPublicMembers()
    {
        $container = $this->createContainerWithAutoconfiguredAttributes();

        foreach ([_AttrMeta_PublicProperty::class, _AttrMeta_PrivateProperty::class, _AttrMeta_PrivatePromotedProperty::class, _AttrMeta_ProtectedMethod::class, _AttrMeta_NoAttribute::class] as $class) {
            $container->register($class, $class)->setAutoconfigured(true);
        }
        $container->register('not_autoconfigured', _AttrMeta_NotAutoconfigured::class);
        $container->register('ignored', _AttrMeta_IgnoredAttributes::class)
            ->setAutoconfigured(true)
            ->addTag('container.ignore_attributes');

        $this->processPasses($container);

        $expectedClasses = [
            _AttrMeta_PrivatePromotedProperty::class => [_AttrMeta_PrivatePromotedProperty::class],
            _AttrMeta_PrivateProperty::class => [_AttrMeta_PrivateProperty::class],
            _AttrMeta_ProtectedMethod::class => [_AttrMeta_ProtectedMethod::class],
            _AttrMeta_PublicProperty::class => [_AttrMeta_PublicProperty::class],
        ];
        $this->assertSame($expectedClasses, $container->getDefinition('serializer.mapping.attribute_loader')->getArgument(1));
    }

    public function testNonPublicMembersAreLoadedWhenAttributesAreDiscoveredAtCompileTimeOnly()
    {
        $container = $this->createContainerWithAutoconfiguredAttributes();
        $container->register('dto', _AttrMeta_PrivatePromotedProperty::class)->setAutoconfigured(true);

        $this->processPasses($container);

        $loader = new AttributeLoader(false, $container->getDefinition('serializer.mapping.attribute_loader')->getArgument(1));
        $metadata = new ClassMetadata(_AttrMeta_PrivatePromotedProperty::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));
        $this->assertSame(['read'], $metadata->getAttributesMetadata()['id']->getGroups());
        $this->assertSame('identifier', $metadata->getAttributesMetadata()['id']->getSerializedName());
    }

    public function testNonPublicMembersAreIgnoredWhenAttributesAreNotAutoconfigured()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([true, []]);
        $container->register('dto', _AttrMeta_PrivateProperty::class)->setAutoconfigured(true);

        $this->processPasses($container);

        $this->assertSame([true, []], $container->getDefinition('serializer.mapping.attribute_loader')->getArguments());
    }

    private function createContainerWithAutoconfiguredAttributes(): ContainerBuilder
    {
        if (!method_exists(ContainerBuilder::class, 'getAttributeAutoconfigurators')) {
            $this->markTestSkipped('Autoconfiguring these attributes requires symfony/dependency-injection 7.3 or higher.');
        }

        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);

        $configurator = static function (ChildDefinition $definition, object $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector) {
            $definition->addTag('serializer.attribute_metadata');
        };
        $container->registerAttributeForAutoconfiguration(Groups::class, $configurator);
        $container->registerAttributeForAutoconfiguration(SerializedName::class, $configurator);

        return $container;
    }

    private function processPasses(ContainerBuilder $container): void
    {
        (new AttributeAutoconfigurationPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
        (new AttributeMetadataPass())->process($container);
    }
}

class _AttrMeta_Source
{
    public string $name;

    public function getName()
    {
    }
}

class _AttrMeta_Target
{
    public string $name;

    public function getName()
    {
    }
}

class _AttrMeta_BadSource
{
    public string $extra;
}

class _AttrMeta_PublicProperty
{
    #[Groups(['read'])]
    public string $name;
}

class _AttrMeta_PrivateProperty
{
    #[Groups(['read'])]
    private string $name;
}

class _AttrMeta_PrivatePromotedProperty
{
    public function __construct(
        #[Groups(['read'])]
        #[SerializedName('identifier')]
        private int $id,
    ) {
    }
}

class _AttrMeta_ProtectedMethod
{
    #[Groups(['read'])]
    protected function getName(): string
    {
        return '';
    }
}

class _AttrMeta_NoAttribute
{
    private string $name;
}

class _AttrMeta_NotAutoconfigured
{
    #[Groups(['read'])]
    private string $name;
}

class _AttrMeta_IgnoredAttributes
{
    #[Groups(['read'])]
    private string $name;
}
