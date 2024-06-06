<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\ClassMapArgument;

class ClassMapArgumentTest extends TestCase
{
    /**
     * @testWith ["App\\Foo"]
     *           ["\\App\\Foo"]
     *           ["App\\Foo\\"]
     */
    public function testNamespaceNormalization(string $namespace)
    {
        $argument = new ClassMapArgument($namespace, 'path');

        self::assertSame('App\\Foo\\', $argument->namespace);
    }

    public function testGetValues()
    {
        $argument = new ClassMapArgument('App\\Foo', 'path', indexBy: 'foo');

        self::assertSame([
            'namespace' => 'App\\Foo\\',
            'path' => 'path',
            'instance_of' => null,
            'with_attribute' => null,
            'index_by' => 'foo',
        ], $argument->getValues());
    }
}
