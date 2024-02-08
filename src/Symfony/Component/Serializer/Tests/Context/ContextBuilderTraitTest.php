<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Context\ContextBuilderInterface;
use Symfony\Component\Serializer\Context\ContextBuilderTrait;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
class ContextBuilderTraitTest extends TestCase
{
    public function testWithContext()
    {
        $contextBuilder = new class() implements ContextBuilderInterface {
            use ContextBuilderTrait;
        };

        $context = $contextBuilder->withContext(['foo' => 'bar'])->toArray();

        $this->assertSame(['foo' => 'bar'], $context);

        $withContextBuilderObject = $contextBuilder->withContext($contextBuilder->withContext(['foo' => 'bar']))->toArray();

        $this->assertSame(['foo' => 'bar'], $withContextBuilderObject);
    }

    public function testWith()
    {
        $contextBuilder = new class() {
            use ContextBuilderTrait;

            public function withFoo(string $value): static
            {
                return $this->with('foo', $value);
            }
        };

        $context = $contextBuilder->withFoo('bar')->toArray();

        $this->assertSame(['foo' => 'bar'], $context);
    }
}
