<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\TypedReference;

class AutowireLocatorTest extends TestCase
{
    public function testSimpleLocator()
    {
        $locator = new AutowireLocator(['foo', 'bar']);

        $this->assertEquals(
            new ServiceLocatorArgument(['foo' => new TypedReference('foo', 'foo'), 'bar' => new TypedReference('bar', 'bar')]),
            $locator->value,
        );
    }

    public function testComplexLocator()
    {
        $locator = new AutowireLocator([
            '?qux',
            'foo' => 'bar',
            'bar' => '?baz',
        ]);

        $this->assertEquals(
            new ServiceLocatorArgument([
                'qux' => new TypedReference('qux', 'qux', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                'foo' => new TypedReference('bar', 'bar', name: 'foo'),
                'bar' => new TypedReference('baz', 'baz', ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'bar'),
            ]),
            $locator->value,
        );
    }

    public function testInvalidTypeLocator()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"bool" is not a PHP type for key "stdClass".');

        new AutowireLocator([
            \stdClass::class => true,
        ]);
    }
}
