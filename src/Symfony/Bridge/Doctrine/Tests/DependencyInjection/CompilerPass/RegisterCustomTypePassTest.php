<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterCustomTypePass;
use Symfony\Bridge\Doctrine\Tests\Fixtures\Type\CustomType;
use Symfony\Bridge\Doctrine\Tests\Fixtures\Type\CustomTypeNoName;
use Symfony\Bridge\Doctrine\Tests\Fixtures\Type\StringWrapperType;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class RegisterCustomTypePassTest extends TestCase
{
    public function testRegisteredWithExplicitName()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);
        $container->setDefinition(CustomType::class, new Definition(CustomType::class));

        (new RegisterCustomTypePass())->process($container);

        $expected = [
            'custom' => ['class' => CustomType::class],
        ];
        $this->assertSame($expected, $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testRegisteredWithoutNameDefaultsToClassName()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);
        $container->setDefinition(CustomTypeNoName::class, new Definition(CustomTypeNoName::class));

        (new RegisterCustomTypePass())->process($container);

        $expected = [
            CustomTypeNoName::class => ['class' => CustomTypeNoName::class],
        ];
        $this->assertSame($expected, $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testDoesNotOverrideExistingTypes()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', [
            'custom' => ['class' => 'App\ExistingType'],
        ]);
        $container->setDefinition(CustomType::class, new Definition(CustomType::class));

        (new RegisterCustomTypePass())->process($container);

        $expected = [
            'custom' => ['class' => 'App\ExistingType'],
        ];
        $this->assertSame($expected, $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testPreservesExistingTypes()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', ['foo' => 'bar']);
        $container->setDefinition(CustomType::class, new Definition(CustomType::class));

        (new RegisterCustomTypePass())->process($container);

        $expected = [
            'foo' => 'bar',
            'custom' => ['class' => CustomType::class],
        ];
        $this->assertSame($expected, $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testSkipsWhenParameterIsMissing()
    {
        $container = new ContainerBuilder();
        (new RegisterCustomTypePass())->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testSkipsTypeWithoutAttribute()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);
        $container->setDefinition(StringWrapperType::class, new Definition(StringWrapperType::class));

        (new RegisterCustomTypePass())->process($container);

        $this->assertSame([], $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testMultipleTypesRegistered()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);
        $container->setDefinition(CustomType::class, new Definition(CustomType::class));
        $container->setDefinition(CustomTypeNoName::class, new Definition(CustomTypeNoName::class));

        (new RegisterCustomTypePass())->process($container);

        $expected = [
            'custom' => ['class' => CustomType::class],
            CustomTypeNoName::class => ['class' => CustomTypeNoName::class],
        ];
        $this->assertSame($expected, $container->getParameter('doctrine.dbal.connection_factory.types'));
    }
}
