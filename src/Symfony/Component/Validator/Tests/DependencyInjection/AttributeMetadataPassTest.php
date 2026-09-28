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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AttributeAutoconfigurationPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\DependencyInjection\AttributeMetadataPass;
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Validation;

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

    public function testProcessPassesMappedClassesToThePropertyInfoCacheWarmer()
    {
        $container = new ContainerBuilder();
        $container->register('validator.builder')
            ->addMethodCall('addMappedClasses', [['validation.yaml' => ['App\Entity\FromFile' => 0, 'App\Entity\User' => 1]]]);
        $container->register('property_info.cache_warmer')
            ->setArguments([null, ['App\Entity\Order'], 'property_info.php']);

        $container->register('service1', 'App\Entity\User')
            ->addTag('validator.attribute_metadata');
        $container->register('service2', 'App\Entity\Product')
            ->addTag('validator.attribute_metadata');

        (new AttributeMetadataPass())->process($container);

        $expectedClasses = [
            'App\Entity\FromFile',
            'App\Entity\Order',
            'App\Entity\Product',
            'App\Entity\User',
        ];
        $this->assertSame($expectedClasses, $container->getDefinition('property_info.cache_warmer')->getArgument(1));
    }

    public function testProcessDiscoversConstraintsOnNonPublicMembers()
    {
        $container = $this->createContainerWithAutoconfiguredConstraints();

        foreach ([_AttrMeta_PublicProperty::class, _AttrMeta_PrivateProperty::class, _AttrMeta_PrivatePromotedProperty::class, _AttrMeta_PrivateCallback::class, _AttrMeta_NoConstraint::class] as $class) {
            $container->register($class, $class)->setAutoconfigured(true);
        }
        $container->register('not_autoconfigured', _AttrMeta_NotAutoconfigured::class);
        $container->register('ignored', _AttrMeta_IgnoredAttributes::class)
            ->setAutoconfigured(true)
            ->addTag('container.ignore_attributes');

        $this->processPasses($container);

        $methodCalls = $container->getDefinition('validator.builder')->getMethodCalls();
        $this->assertSame('addAttributeMappings', $methodCalls[0][0]);

        $expectedClasses = [
            _AttrMeta_PrivateCallback::class => [_AttrMeta_PrivateCallback::class],
            _AttrMeta_PrivatePromotedProperty::class => [_AttrMeta_PrivatePromotedProperty::class],
            _AttrMeta_PrivateProperty::class => [_AttrMeta_PrivateProperty::class],
            _AttrMeta_PublicProperty::class => [_AttrMeta_PublicProperty::class],
        ];
        $this->assertSame([$expectedClasses], $methodCalls[0][1]);
    }

    public function testNonPublicMembersAreValidatedWhenConstraintsAreDiscoveredAtCompileTimeOnly()
    {
        $container = $this->createContainerWithAutoconfiguredConstraints();
        $container->register('dto', _AttrMeta_PrivatePromotedProperty::class)->setAutoconfigured(true);

        $this->processPasses($container);

        $builder = Validation::createValidatorBuilder();
        foreach ($container->getDefinition('validator.builder')->getMethodCalls() as [$method, $arguments]) {
            $builder->$method(...$arguments);
        }

        $this->assertCount(1, $builder->getValidator()->validate(new _AttrMeta_PrivatePromotedProperty('')));
    }

    public function testNonPublicMembersAreIgnoredWhenConstraintsAreNotAutoconfigured()
    {
        $container = new ContainerBuilder();
        $container->register('validator.builder');
        $container->register('dto', _AttrMeta_PrivateProperty::class)->setAutoconfigured(true);

        $this->processPasses($container);

        $this->assertSame([], $container->getDefinition('validator.builder')->getMethodCalls());
    }

    private function createContainerWithAutoconfiguredConstraints(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('validator.builder');
        $container->registerAttributeForAutoconfiguration(Constraint::class, static function (ChildDefinition $definition, Constraint $attribute, \ReflectionClass|\ReflectionMethod|\ReflectionProperty $reflector) {
            $definition->addTag('validator.attribute_metadata');
        });

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
    #[Assert\NotBlank]
    public string $name = '';
}

class _AttrMeta_PrivateProperty
{
    #[Assert\NotBlank]
    private string $name = '';
}

class _AttrMeta_PrivatePromotedProperty
{
    public function __construct(
        #[Assert\NotBlank]
        private string $name,
    ) {
    }
}

class _AttrMeta_PrivateCallback
{
    #[Assert\Callback]
    private function validate(ExecutionContextInterface $context): void
    {
    }
}

class _AttrMeta_NoConstraint
{
    private string $name = '';
}

class _AttrMeta_NotAutoconfigured
{
    #[Assert\NotBlank]
    private string $name = '';
}

class _AttrMeta_IgnoredAttributes
{
    #[Assert\NotBlank]
    private string $name = '';
}
