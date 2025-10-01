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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
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
        $this->assertSame([false, []], $arguments);
    }

    public function testProcessWithTaggedServices()
    {
        $container = new ContainerBuilder();
        $container->setParameter('user_entity.class', 'App\Entity\User');

        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);

        $container->register('service1', '%user_entity.class%')
            ->addTag('serializer.attribute_metadata')
            ->addTag('container.excluded');
        $container->register('service2', 'App\Entity\Product')
            ->addTag('serializer.attribute_metadata')
            ->addTag('container.excluded');
        $container->register('service3', 'App\Entity\Order')
            ->addTag('serializer.attribute_metadata')
            ->addTag('container.excluded');
        // Classes should be deduplicated
        $container->register('service4', 'App\Entity\Order')
            ->addTag('serializer.attribute_metadata')
            ->addTag('container.excluded');

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

    public function testThrowsWhenMissingExcludedTag()
    {
        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader');

        $container->register('service_without_excluded', 'App\\Entity\\User')
            ->addTag('serializer.attribute_metadata');

        $this->expectException(InvalidArgumentException::class);
        (new AttributeMetadataPass())->process($container);
    }

    public function testProcessWithForOptionAndMatchingMembers()
    {
        $sourceClass = _AttrMeta_Source::class;
        $targetClass = _AttrMeta_Target::class;

        $container = new ContainerBuilder();
        $container->register('serializer.mapping.attribute_loader', AttributeLoader::class)
            ->setArguments([false, []]);

        $container->register('service.source', $sourceClass)
            ->addTag('serializer.attribute_metadata', ['for' => $targetClass])
            ->addTag('container.excluded');

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
            ->addTag('serializer.attribute_metadata', ['for' => $targetClass])
            ->addTag('container.excluded');

        $this->expectException(MappingException::class);
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
