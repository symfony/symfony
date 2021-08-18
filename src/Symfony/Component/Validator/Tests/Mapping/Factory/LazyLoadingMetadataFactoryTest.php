<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\EntityParent;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\PropertyGetter;
use Symfony\Component\Validator\Tests\Fixtures\PropertyGetterInterface;

class LazyLoadingMetadataFactoryTest extends TestCase
{
    private const CLASS_NAME = Entity::class;
    private const PARENT_CLASS = EntityParent::class;
    private const INTERFACE_A_CLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityInterfaceA';

    public function testLoadClassMetadataWithInterface()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::PARENT_CLASS);

        $constraints = [
            new ConstraintA(['groups' => ['Default', 'EntityParent']]),
            new ConstraintA(['groups' => ['Default', 'EntityInterfaceA', 'EntityParent']]),
        ];

        $this->assertEquals($constraints, $metadata->getConstraints());
    }

    public function testMergeParentConstraints()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::CLASS_NAME);

        $constraints = [
            new ConstraintA(['groups' => [
                'Default',
                'Entity',
            ]]),
            new ConstraintA(['groups' => [
                'Default',
                'EntityParent',
                'Entity',
            ]]),
            new ConstraintA(['groups' => [
                'Default',
                'EntityInterfaceA',
                'EntityParent',
                'Entity',
            ]]),
            new ConstraintA(['groups' => [
                'Default',
                'EntityInterfaceB',
                'Entity',
            ]]),
            new ConstraintA(['groups' => [
                'Default',
                'EntityParentInterface',
                'Entity',
            ]]),
        ];

        $this->assertEquals($constraints, $metadata->getConstraints());
    }

    public function testCachedMetadata()
    {
        $cache = new ArrayAdapter();
        $factory = new LazyLoadingMetadataFactory(new TestLoader(), $cache);

        $expectedConstraints = [
            new ConstraintA(['groups' => ['Default', 'EntityParent']]),
            new ConstraintA(['groups' => ['Default', 'EntityInterfaceA', 'EntityParent']]),
        ];

        $metadata = $factory->getMetadataFor(self::PARENT_CLASS);

        $this->assertEquals(self::PARENT_CLASS, $metadata->getClassName());
        $this->assertEquals($expectedConstraints, $metadata->getConstraints());

        $loader = $this->createMock(LoaderInterface::class);
        $loader->expects($this->never())->method('loadClassMetadata');

        $factory = new LazyLoadingMetadataFactory($loader, $cache);

        $metadata = $factory->getMetadataFor(self::PARENT_CLASS);

        $this->assertEquals(self::PARENT_CLASS, $metadata->getClassName());
        $this->assertEquals($expectedConstraints, $metadata->getConstraints());
    }

    public function testNonClassNameStringValues()
    {
        $this->expectException(NoSuchMetadataException::class);
        $testedValue = 'error@example.com';
        $loader = $this->createMock(LoaderInterface::class);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $factory = new LazyLoadingMetadataFactory($loader, $cache);
        $cache
            ->expects($this->never())
            ->method('getItem');
        $factory->getMetadataFor($testedValue);
    }

    public function testMetadataCacheWithRuntimeConstraint()
    {
        $cache = new ArrayAdapter();
        $factory = new LazyLoadingMetadataFactory(new TestLoader(), $cache);

        $metadata = $factory->getMetadataFor(self::PARENT_CLASS);
        $metadata->addConstraint(new Callback(function () {}));

        $this->assertCount(3, $metadata->getConstraints());

        $metadata = $factory->getMetadataFor(self::CLASS_NAME);

        $this->assertCount(6, $metadata->getConstraints());
    }

    public function testGroupsFromParent()
    {
        $reader = new \Symfony\Component\Validator\Mapping\Loader\StaticMethodLoader();
        $factory = new LazyLoadingMetadataFactory($reader);
        $metadata = $factory->getMetadataFor('Symfony\Component\Validator\Tests\Fixtures\EntityStaticCarTurbo');
        $groups = [];

        foreach ($metadata->getPropertyMetadata('wheels') as $propertyMetadata) {
            $constraints = $propertyMetadata->getConstraints();
            $groups = array_replace($groups, $constraints[0]->groups);
        }

        $this->assertCount(4, $groups);
        $this->assertContains('Default', $groups);
        $this->assertContains('EntityStaticCarTurbo', $groups);
        $this->assertContains('EntityStaticCar', $groups);
        $this->assertContains('EntityStaticVehicle', $groups);
    }

    public function testMultipathInterfaceConstraint()
    {
        $factory = new LazyLoadingMetadataFactory(new PropertyGetterInterfaceConstraintLoader());
        $metadata = $factory->getMetadataFor(PropertyGetter::class);
        $constraints = $metadata->getPropertyMetadata('property');

        $this->assertCount(1, $constraints);
    }
}

class TestLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $metadata->addConstraint(new ConstraintA());

        return true;
    }
}

class PropertyGetterInterfaceConstraintLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        if (PropertyGetterInterface::class === $metadata->getClassName()) {
            $metadata->addGetterConstraint('property', new NotBlank());
        }

        return true;
    }
}
