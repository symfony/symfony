<?php

declare(strict_types=1);

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
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\AliasPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AsAliasAttributeTest extends TestCase
{
    public function testProcessWithMissingAliasAttribute()
    {
        $container = new ContainerBuilder();

        $container->register('foo', \DateTimeImmutable::class)
            ->addTag(AsAlias::class, []);

        self::expectException(\InvalidArgumentException::class);

        (new AliasPass())->process($container);
    }

    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container->register(HelloWorldService::class)
            ->setAutowired(true)
            ->setAutoconfigured(true);

        $container->registerAttributeForAutoconfiguration(AsAlias::class, static function (ChildDefinition $definition, AsAlias $attribute, \ReflectionClass $reflector): void {
            $definition->addTag(
                AsAlias::class,
                [
                    'alias' => $attribute->alias,
                ]
            );
        });

        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->compile();

        self::assertTrue($container->hasAlias('bar'));
        self::assertSame(HelloWorldService::class, (string) $container->getAlias('bar'));
    }
}

#[AsAlias(alias: 'bar')]
class HelloWorldService
{
}
