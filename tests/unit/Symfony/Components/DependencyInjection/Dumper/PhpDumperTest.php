<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\DependencyInjection\Builder;
use Symfony\Components\DependencyInjection\Dumper\PhpDumper;

$t = new LimeTest(5);

$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');

// ->dump()
$t->diag('->dump()');
$dumper = new PhpDumper($container = new Builder());

$t->is($dumper->dump(), file_get_contents($fixturesPath.'/php/services1.php'), '->dump() dumps an empty container as an empty PHP class');
$t->is($dumper->dump(array('class' => 'Container', 'base_class' => 'AbstractContainer')), file_get_contents($fixturesPath.'/php/services1-1.php'), '->dump() takes a class and a base_class options');

$container = new Builder();
$dumper = new PhpDumper($container);

// ->addParameters()
$t->diag('->addParameters()');
$container = include $fixturesPath.'/containers/container8.php';
$dumper = new PhpDumper($container);
$t->is($dumper->dump(), file_get_contents($fixturesPath.'/php/services8.php'), '->dump() dumps parameters');

// ->addService()
$t->diag('->addService()');
$container = include $fixturesPath.'/containers/container9.php';
$dumper = new PhpDumper($container);
$t->is($dumper->dump(), str_replace('%path%', $fixturesPath.'/includes', file_get_contents($fixturesPath.'/php/services9.php')), '->dump() dumps services');

$dumper = new PhpDumper($container = new Builder());
$container->register('foo', 'FooClass')->addArgument(new stdClass());
try
{
  $dumper->dump();
  $t->fail('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
}
catch (RuntimeException $e)
{
  $t->pass('->dump() throws a RuntimeException if the container to be dumped has reference to objects or resources');
}
