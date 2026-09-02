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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Serializer\DependencyInjection\AttributeMetadataPass;
use Symfony\Component\Serializer\Exception\MappingException;
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
        $this->assertSame([false, [], []], $arguments, 'An empty discriminator map type registry should still be wired.');
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
        $this->assertSame([false, $expectedClasses, []], $arguments);
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
        $this->assertSame([false, [$targetClass => [$sourceClass]], []], $arguments);
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

    public function testProcessWithDiscriminatorMapType()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);
        $container->register('service.source', _AttrMeta_DiscriminatorChild::class)
            ->addTag('serializer.attribute_metadata', ['for' => _AttrMeta_DiscriminatorParent::class, 'type' => 'child', 'discriminator_map_type' => true]);
        $container->register('service.same_source', _AttrMeta_DiscriminatorChild::class)
            ->addTag('serializer.attribute_metadata', ['for' => _AttrMeta_DiscriminatorParent::class, 'type' => 'child', 'discriminator_map_type' => true]);

        (new AttributeMetadataPass())->process($container);

        $arguments = $container->getDefinition('serializer.mapping.attribute_loader')->getArguments();
        $this->assertSame([false, [], [_AttrMeta_DiscriminatorParent::class => ['child' => _AttrMeta_DiscriminatorChild::class]]], $arguments);
    }

    public function testProcessCollectsSelfContributedDiscriminatorMapType()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);
        $container->register('service.self', _AttrMeta_DiscriminatorParent::class)
            ->addTag('serializer.attribute_metadata', ['for' => _AttrMeta_DiscriminatorParent::class, 'type' => 'parent', 'discriminator_map_type' => true]);

        (new AttributeMetadataPass())->process($container);

        $arguments = $container->getDefinition('serializer.mapping.attribute_loader')->getArguments();
        $this->assertSame([false, [], [_AttrMeta_DiscriminatorParent::class => ['parent' => _AttrMeta_DiscriminatorParent::class]]], $arguments);
    }

    public function testProcessRejectsDiscriminatorMapTypeOnUnknownClass()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);
        $container->register('service.source', 'App\MissingChild')
            ->addTag('serializer.attribute_metadata', ['for' => _AttrMeta_DiscriminatorParent::class, 'type' => 'child', 'discriminator_map_type' => true]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Class "App\MissingChild" cannot add a discriminator map type for "%s" because it cannot be found.', _AttrMeta_DiscriminatorParent::class));
        (new AttributeMetadataPass())->process($container);
    }

    public function testProcessRejectsDiscriminatorMapTypeForUnknownTargetClass()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);
        $container->register('service.source', _AttrMeta_DiscriminatorChild::class)
            ->addTag('serializer.attribute_metadata', ['for' => 'App\MissingParent', 'type' => 'child', 'discriminator_map_type' => true]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Class "%s" cannot add a discriminator map type for "App\MissingParent" because the target class cannot be found.', _AttrMeta_DiscriminatorChild::class));
        (new AttributeMetadataPass())->process($container);
    }

    public function testProcessRejectsUnknownTargetClass()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);
        $container->register('service.source', _AttrMeta_Source::class)
            ->addTag('serializer.attribute_metadata', ['for' => 'App\MissingTarget']);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Class "%s" cannot extend serialization for "App\MissingTarget" because the target class cannot be found.', _AttrMeta_Source::class));
        (new AttributeMetadataPass())->process($container);
    }

    public function testProcessRejectsDiscriminatorMapTypeThatIsNotASubtype()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);
        $container->register('service.source', _AttrMeta_Source::class)
            ->addTag('serializer.attribute_metadata', ['for' => _AttrMeta_DiscriminatorParent::class, 'type' => 'source', 'discriminator_map_type' => true]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage('is not a subtype');
        (new AttributeMetadataPass())->process($container);
    }

    public function testProcessRejectsDuplicateDiscriminatorMapTypes()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);
        $container->register('service.first', _AttrMeta_DiscriminatorChild::class)
            ->addTag('serializer.attribute_metadata', ['for' => _AttrMeta_DiscriminatorParent::class, 'type' => 'child', 'discriminator_map_type' => true]);
        $container->register('service.second', _AttrMeta_SecondDiscriminatorChild::class)
            ->addTag('serializer.attribute_metadata', ['for' => _AttrMeta_DiscriminatorParent::class, 'type' => 'child', 'discriminator_map_type' => true]);

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(\sprintf('Discriminator map type "child" for "%s" is already mapped to "%s".', _AttrMeta_DiscriminatorParent::class, _AttrMeta_DiscriminatorChild::class));
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

class _AttrMeta_DiscriminatorParent
{
}

class _AttrMeta_DiscriminatorChild extends _AttrMeta_DiscriminatorParent
{
}

class _AttrMeta_SecondDiscriminatorChild extends _AttrMeta_DiscriminatorParent
{
}
