<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DebugSectionNamesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class DebugSectionNamesPassTest extends TestCase
{
    public function testCollectsSectionNamesSortedByPriority()
    {
        $container = $this->createContainer();
        $container->register('section.low', \stdClass::class)->addTag('debug.section', ['name' => 'low', 'priority' => 10]);
        $container->register('section.high', \stdClass::class)->addTag('debug.section', ['name' => 'high', 'priority' => 20]);

        (new DebugSectionNamesPass())->process($container);

        $this->assertSame(['high', 'low'], $container->getDefinition('console.command.debug')->getArgument(1));
    }

    public function testMissingNameAttributeThrows()
    {
        $container = $this->createContainer();
        $container->register('console.command.debug.section.foo', \stdClass::class)->addTag('debug.section', ['priority' => 10]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Add a valid "name" attribute to the tag/');

        (new DebugSectionNamesPass())->process($container);
    }

    public function testReservedNameThrows()
    {
        $container = $this->createContainer();
        $container->register('section.help', \stdClass::class)->addTag('debug.section', ['name' => 'help', 'priority' => 10]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be "help"/');

        (new DebugSectionNamesPass())->process($container);
    }

    public function testNoopWithoutTheDebugCommand()
    {
        $container = new ContainerBuilder();
        $container->register('some.section', \stdClass::class)->addTag('debug.section', ['name' => '!!invalid!!']);

        (new DebugSectionNamesPass())->process($container);

        $this->assertFalse($container->hasDefinition('console.command.debug'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('console.command.debug', \stdClass::class)->setArguments([null, []]);

        return $container;
    }
}
