<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\DependencyInjection\AttributeMetadataPass;
use Symfony\Component\Validator\Exception\MappingException;

class AttributeMetadataPassTest extends TestCase
{
    public function testProcessWithNoValidatorBuilder()
    {
        $container = new ContainerBuilder();

        // Should not throw any exception
        (new AttributeMetadataPass())->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testProcessWithValidatorBuilderButNoTaggedServices()
    {
        $container = new ContainerBuilder();
        $container->register('validator.builder');

        $pass = new AttributeMetadataPass();
        $pass->process($container);

        $methodCalls = $container->getDefinition('validator.builder')->getMethodCalls();
        $this->assertCount(0, $methodCalls);
    }

    public function testProcessWithTaggedServices()
    {
        $container = new ContainerBuilder();
        $container->setParameter('user_entity.class', 'App\Entity\User');
        $container->register('validator.builder')
            ->addMethodCall('addAttributeMappings', [[]]);

        $container->register('service1', '%user_entity.class%')
            ->addTag('validator.attribute_metadata');
        $container->register('service2', 'App\Entity\Product')
            ->addTag('validator.attribute_metadata');
        $container->register('service3', 'App\Entity\Order')
            ->addTag('validator.attribute_metadata');
        // Classes should be deduplicated
        $container->register('service4', 'App\Entity\Order')
            ->addTag('validator.attribute_metadata');

        (new AttributeMetadataPass())->process($container);

        $methodCalls = $container->getDefinition('validator.builder')->getMethodCalls();
        $this->assertCount(2, $methodCalls);
        $this->assertEquals('addAttributeMappings', $methodCalls[1][0]);

        // Classes should be sorted alphabetically
        $expectedClasses = [
            'App\Entity\Order' => ['App\Entity\Order'],
            'App\Entity\Product' => ['App\Entity\Product'],
            'App\Entity\User' => ['App\Entity\User'],
        ];
        $this->assertEquals([$expectedClasses], $methodCalls[1][1]);
    }

    public function testProcessWithForOptionAndMatchingMembers()
    {
        $sourceClass = _AttrMeta_Source::class;
        $targetClass = _AttrMeta_Target::class;

        $container = new ContainerBuilder();
        $container->register('validator.builder');

        $container->register('service.source', $sourceClass)
            ->addTag('validator.attribute_metadata', ['for' => $targetClass]);

        (new AttributeMetadataPass())->process($container);

        $methodCalls = $container->getDefinition('validator.builder')->getMethodCalls();
        $this->assertNotEmpty($methodCalls);
        $this->assertSame('addAttributeMappings', $methodCalls[0][0]);
        $this->assertSame([$targetClass => [$sourceClass]], $methodCalls[0][1][0]);
    }

    public function testProcessWithForOptionAndMissingMemberThrows()
    {
        $sourceClass = _AttrMeta_BadSource::class;
        $targetClass = _AttrMeta_Target::class;

        $container = new ContainerBuilder();
        $container->register('validator.builder');

        $container->register('service.source', $sourceClass)
            ->addTag('validator.attribute_metadata', ['for' => $targetClass]);

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
