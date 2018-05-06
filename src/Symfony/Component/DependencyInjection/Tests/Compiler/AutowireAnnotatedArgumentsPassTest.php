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
use Symfony\Component\DependencyInjection\Compiler\ResolveClassPass;
use Symfony\Component\DependencyInjection\Compiler\AutowireAnnotatedArgumentsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class AutowireAnnotatedArgumentsPassTest extends TestCase
{
    public function testSetterInjection()
    {
        $container = new ContainerBuilder();
        $container->setParameter('a', 'a');
        $container->setParameter('b', 'b');
        $container->register('c');
        $container->register('d');
        $foo = $container->register(AnnotatedParamsFoo::class)
            ->setArguments(array(0 => 'A', 2 => new Reference('C')))
            ->setAutowired(true)
        ;

        (new ResolveClassPass())->process($container);
        (new AutowireAnnotatedArgumentsPass())->process($container);

        $expected = array(
            'A',
            'b',
            new Reference('C'),
            new Reference('d'),
        );
        $this->assertEquals($expected, $foo->getArguments());
    }
}
