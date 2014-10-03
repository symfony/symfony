<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\SuperClosure\Tests;

use Symfony\Bridge\SuperClosure\ClosureDumper\SuperClosureDumper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Integration tests of {@see \Symfony\Component\DependencyInjection\Dumper\PhpDumper} and SuperClosureDumper
 *
 * @author Nikita Konstantinov <unk91nd@gmail.com>
 */
class PhpDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testThatPhpDumperCanDumpClosure()
    {
        $container = new ContainerBuilder();

        $container->setParameter('baz', 42);

        $container
            ->register('foo', 'stdClass')
            ->setFactory(function ($baz) {
                $foo = new \stdClass();
                $foo->foo = $baz;

                return $foo;
            })
            ->addArgument('%baz%')
        ;

        $container
            ->register('bar', 'stdClass')
            ->setFactory(function (\stdClass $foo) {
                $bar = clone $foo;
                $bar->bar = 'bar';

                return $bar;
            })
            ->addArgument(new Reference('foo'))
        ;

        $container->compile();

        $dumper = new PhpDumper($container);
        $dumper->setClosureDumper(new SuperClosureDumper());

        $options = array('class' => 'ProjectServiceContainerWithClosures');

        $this->assertStringEqualsFile(__DIR__.'/Fixtures/php/services_with_closure_factory.php', $dumper->dump($options));
    }

    public function testThatDumpedContainerWorks()
    {
        require_once __DIR__.'/Fixtures/php/services_with_closure_factory.php';

        $container = new \ProjectServiceContainerWithClosures();

        $expectedBar = new \stdClass();
        $expectedBar->foo = 42;
        $expectedBar->bar = 'bar';

        $this->assertEquals($expectedBar, $container->get('bar'));
    }
}
