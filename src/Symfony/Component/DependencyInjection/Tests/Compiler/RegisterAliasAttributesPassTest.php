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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AliasAttributed;

/**
 * @requires PHP 8
 */
class RegisterAliasAttributesPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AliasAttributed::class)
            ->setAutoconfigured(true);

        (new RegisterAliasAttributesPass())->process($container);

        $this->assertEquals(AliasAttributed::class, $container->getAlias('my_alias_attribute'));
    }

    public function testIgnoreAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('foo', AliasAttributed::class)
            ->addTag('container.ignore_attributes')
            ->setAutoconfigured(true);

        (new RegisterAliasAttributesPass())->process($container);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The service alias "my_alias_attribute" does not exist.');
        $container->getAlias('my_alias_attribute');
    }
}
