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
use Symfony\Component\DependencyInjection\Compiler\AliasDeprecatedPublicServicesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class AliasDeprecatedPublicServicesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(true)
            ->addTag('container.private', ['package' => 'foo/bar', 'version' => '1.2']);

        (new AliasDeprecatedPublicServicesPass())->process($container);

        self::assertTrue($container->hasAlias('foo'));

        $alias = $container->getAlias('foo');

        self::assertSame('.container.private.foo', (string) $alias);
        self::assertTrue($alias->isPublic());
        self::assertFalse($alias->isPrivate());
        self::assertSame([
            'package' => 'foo/bar',
            'version' => '1.2',
            'message' => 'Accessing the "foo" service directly from the container is deprecated, use dependency injection instead.',
        ], $alias->getDeprecation('foo'));
    }

    /**
     * @dataProvider processWithMissingAttributeProvider
     */
    public function testProcessWithMissingAttribute(string $attribute, array $attributes)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage(sprintf('The "%s" attribute is mandatory for the "container.private" tag on the "foo" service.', $attribute));

        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->addTag('container.private', $attributes);

        (new AliasDeprecatedPublicServicesPass())->process($container);
    }

    public function processWithMissingAttributeProvider()
    {
        return [
            ['package', ['version' => '1.2']],
            ['version', ['package' => 'foo/bar']],
        ];
    }

    public function testProcessWithNonPublicService()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->addTag('container.private', ['package' => 'foo/bar', 'version' => '1.2']);

        (new AliasDeprecatedPublicServicesPass())->process($container);

        self::assertTrue($container->hasDefinition('foo'));
    }
}
