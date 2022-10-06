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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

class AutowireTest extends TestCase
{
    public function testCanOnlySetOneParameter()
    {
        $this->expectException(LogicException::class);

        new Autowire(service: 'id', expression: 'expr');
    }

    public function testMustSetOneParameter()
    {
        $this->expectException(LogicException::class);

        new Autowire();
    }

    public function testCanUseZeroForValue()
    {
        $this->assertSame('0', (new Autowire(value: '0'))->value);
    }

    public function testCanUseArrayForValue()
    {
        $this->assertSame(['FOO' => 'BAR'], (new Autowire(value: ['FOO' => 'BAR']))->value);
    }

    public function testCanUseValueWithAtSign()
    {
        $this->assertInstanceOf(Reference::class, (new Autowire(value: '@service'))->value);
    }

    public function testCanUseValueWithDoubleAtSign()
    {
        $this->assertSame('@service', (new Autowire(value: '@@service'))->value);
    }

    public function testCanUseValueWithAtAndEqualSign()
    {
        $this->assertInstanceOf(Expression::class, (new Autowire(value: '@=service'))->value);
    }
}
