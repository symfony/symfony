<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\SyntaxAware\Tests;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\SyntaxAware\SyntaxAwareContainerBuilder;

class SyntaxAwareContainerBuilderTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructWrapsParameterBag()
    {
        $container = new SyntaxAwareContainerBuilder();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\SyntaxAware\SyntaxAwareParameterBag', $container->getParameterBag());
        $outerParamBag = $container->getParameterBag();
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\ParameterBag\ParameterBag', $outerParamBag->getParameterBag());

        $innerBag = new ParameterBag(array('foo' => 'bar'));
        $container = new SyntaxAwareContainerBuilder($innerBag);
        $outerParamBag = $container->getParameterBag();
        $this->assertSame($innerBag, $outerParamBag->getParameterBag());
    }

    public function testRegisterReturnsAwareDefinition()
    {
        $container = new SyntaxAwareContainerBuilder();
        $actualDefn = $container->register('foo', 'Acme\Foo');
        $this->assertInstanceOf('Symfony\Component\DependencyInjection\SyntaxAware\SyntaxAwareDefinition', $actualDefn);
        $this->assertEquals('Acme\Foo', $actualDefn->getClass());
    }
}
