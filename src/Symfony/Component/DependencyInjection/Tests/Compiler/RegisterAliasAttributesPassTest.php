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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\RegisterAliasAttributesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Tests\Fixtures\BarInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\WithAsAlias;
use Symfony\Component\DependencyInjection\Tests\Fixtures\WithAsAliasMultiple;
use Symfony\Component\DependencyInjection\Tests\Fixtures\WithAsAliasPublic;

/**
 * @requires PHP 8
 */
class RegisterAliasAttributesPassTest extends TestCase
{
    /**
     * @dataProvider provideClassesWithAsAliasAttributes
     */
    public function testProcess(string $class, array $expectedAliases)
    {
        $container = new ContainerBuilder();
        $container->register($class, $class)
            ->setAutoconfigured(true);

        (new RegisterAliasAttributesPass())->process($container);

        foreach ($expectedAliases as $expectedAlias) {
            $this->assertTrue($container->hasAlias($expectedAlias['id']));
            $this->assertSame($expectedAlias['public'], $container->getAlias($expectedAlias['id'])->isPublic());
        }
    }

    /**
     * @dataProvider provideClassesWithAsAliasAttributes
     */
    public function testAutoconfigureFalse(string $class, array $expectedAliases)
    {
        $container = new ContainerBuilder();
        $container->register($class, $class)
            ->setAutoconfigured(false);

        (new RegisterAliasAttributesPass())->process($container);

        foreach ($expectedAliases as $expectedAlias) {
            $this->assertFalse($container->hasAlias($expectedAlias['id']));
        }
    }

    /**
     * @dataProvider provideClassesWithAsAliasAttributes
     */
    public function testIgnoreAttribute(string $class, array $expectedAliases)
    {
        $container = new ContainerBuilder();
        $container->register($class, $class)
            ->addTag('container.ignore_attributes')
            ->setAutoconfigured(true);

        (new RegisterAliasAttributesPass())->process($container);

        foreach ($expectedAliases as $expectedAlias) {
            $this->assertFalse($container->hasAlias($expectedAlias['id']));
        }
    }

    public function provideClassesWithAsAliasAttributes(): iterable
    {
        yield 'Private' => [WithAsAlias::class, [['id' => BarInterface::class, 'public' => false]]];
        yield 'Public' => [WithAsAliasPublic::class, [['id' => 'some-alias', 'public' => true]]];
        yield 'Multiple' => [WithAsAliasMultiple::class, [
            ['id' => BarInterface::class, 'public' => true],
            ['id' => 'some-alias', 'public' => false],
        ]];
    }

    public function testExceptionIsThrownOnDupliacteAlias()
    {
        $container = new ContainerBuilder();
        $container->register(WithAsAlias::class, WithAsAlias::class)->setAutoconfigured(true);
        $container->register(WithAsAliasMultiple::class, WithAsAliasMultiple::class)->setAutoconfigured(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The service "%s" cannot use the alias "%s" as it is already used by "%s".', WithAsAliasMultiple::class, BarInterface::class, WithAsAlias::class));

        (new RegisterAliasAttributesPass())->process($container);
    }
}
