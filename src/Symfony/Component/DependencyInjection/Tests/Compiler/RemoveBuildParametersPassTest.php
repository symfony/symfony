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
use Symfony\Component\DependencyInjection\Compiler\RemoveBuildParametersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveBuildParametersPassTest extends TestCase
{
    public function testBuildParametersShouldBeRemoved()
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('foo', 'Foo');
        $builder->setParameter('.bar', 'Bar');

        $pass = new RemoveBuildParametersPass();
        $pass->process($builder);

        $this->assertSame('Foo', $builder->getParameter('foo'), '"foo" parameter must be defined.');
        $this->assertFalse($builder->hasParameter('.bar'), '".bar" parameter must be removed.');
        $this->assertSame(['.bar' => 'Bar'], $pass->getRemovedParameters(), '".bar" parameter must be returned with its value.');
    }

    public function testArrayBuildParametersArePreservedWhenConfigured()
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('foo', 'Foo');
        $builder->setParameter('.scalar', 'Bar');
        $builder->setParameter('.array', ['baz' => 'qux']);

        $pass = new RemoveBuildParametersPass(true);
        $pass->process($builder);

        $this->assertSame('Foo', $builder->getParameter('foo'), '"foo" parameter must be defined.');
        $this->assertFalse($builder->hasParameter('.scalar'), '".scalar" parameter must be removed.');
        $this->assertTrue($builder->hasParameter('.array'), '".array" parameter must be preserved.');
        $this->assertSame(['baz' => 'qux'], $builder->getParameter('.array'), '".array" parameter must retain its value.');
        $this->assertSame(['.scalar' => 'Bar'], $pass->getRemovedParameters(), 'Only ".scalar" parameter must be returned as removed.');
    }

    public function testNonArrayBuildParametersAreAlwaysRemoved()
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('.scalar', 'Bar');
        $builder->setParameter('.array', ['baz' => 'qux']);

        $pass = new RemoveBuildParametersPass();
        $pass->process($builder);

        $this->assertFalse($builder->hasParameter('.scalar'), '".scalar" parameter must be removed.');
        $this->assertFalse($builder->hasParameter('.array'), '".array" parameter must be removed.');
        $this->assertSame(['.scalar' => 'Bar', '.array' => ['baz' => 'qux']], $pass->getRemovedParameters(), 'Both parameters must be returned as removed.');
    }
}
