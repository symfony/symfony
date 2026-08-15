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

use Doctrine\ORM\Mapping\ChainTypedFieldMapper;
use Doctrine\ORM\Mapping\DefaultTypedFieldMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass\RegisterDatePointTypePass;
use Symfony\Bridge\Doctrine\Types\DatePointType;
use Symfony\Bridge\Doctrine\Types\DayPointType;
use Symfony\Bridge\Doctrine\Types\TimePointType;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\Clock\DayPoint;
use Symfony\Component\Clock\TimePoint;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RegisterDatePointTypePassTest extends TestCase
{
    public function testRegistered()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', ['foo' => 'bar']);
        (new RegisterDatePointTypePass())->process($container);

        $expected = [
            'foo' => 'bar',
            'date_point' => ['class' => DatePointType::class],
            'day_point' => ['class' => DayPointType::class],
            'time_point' => ['class' => TimePointType::class],
        ];
        $this->assertSame($expected, $container->getParameter('doctrine.dbal.connection_factory.types'));
    }

    public function testTypedFieldMapperIsRegistered()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $definition = new Definition();
        $container->setDefinition('doctrine.orm.default_configuration', $definition);

        (new RegisterDatePointTypePass())->process($container);

        $calls = array_filter($definition->getMethodCalls(), static fn (array $call) => 'setTypedFieldMapper' === $call[0]);
        $this->assertCount(1, $calls);
        $mapperDef = array_values($calls)[0][1][0];
        $this->assertSame(DefaultTypedFieldMapper::class, $mapperDef->getClass());
        $this->assertSame([[DatePoint::class => 'date_point', DayPoint::class => 'day_point', TimePoint::class => 'time_point']], $mapperDef->getArguments());
    }

    public function testAMapperConfiguredByTheApplicationIsChainedFirst()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $definition = new Definition();
        $userMapper = new Reference('app.typed_field_mapper');
        $definition->addMethodCall('setTypedFieldMapper', [$userMapper]);
        $container->setDefinition('doctrine.orm.default_configuration', $definition);

        (new RegisterDatePointTypePass())->process($container);

        $calls = array_filter($definition->getMethodCalls(), static fn (array $call) => 'setTypedFieldMapper' === $call[0]);
        $this->assertCount(1, $calls);
        $chainDef = array_values($calls)[0][1][0];
        $this->assertSame(ChainTypedFieldMapper::class, $chainDef->getClass());
        $this->assertSame($userMapper, $chainDef->getArgument(0));
        $this->assertSame(DefaultTypedFieldMapper::class, $chainDef->getArgument(1)->getClass());
    }

    public function testEveryEntityManagerConfigurationIsCovered()
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.dbal.connection_factory.types', []);

        $container->setDefinition('doctrine.orm.default_configuration', $default = new Definition());
        $container->setDefinition('doctrine.orm.other_configuration', $other = new Definition());
        $container->setDefinition('doctrine.orm.proxy_cache_warmer', $unrelated = new Definition());

        (new RegisterDatePointTypePass())->process($container);

        foreach ([$default, $other] as $definition) {
            $calls = array_filter($definition->getMethodCalls(), static fn (array $call) => 'setTypedFieldMapper' === $call[0]);
            $this->assertCount(1, $calls);
        }
        $this->assertSame([], $unrelated->getMethodCalls());
    }
}
