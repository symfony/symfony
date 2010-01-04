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
use Symfony\Components\DependencyInjection\Dumper\XmlDumper;

$t = new LimeTest(4);

$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');

// ->dump()
$t->diag('->dump()');
$dumper = new XmlDumper($container = new Builder());

$t->is($dumper->dump(), file_get_contents($fixturesPath.'/xml/services1.xml'), '->dump() dumps an empty container as an empty XML file');

$container = new Builder();
$dumper = new XmlDumper($container);

// ->addParameters()
$t->diag('->addParameters()');
$container = include $fixturesPath.'//containers/container8.php';
$dumper = new XmlDumper($container);
$t->is($dumper->dump(), file_get_contents($fixturesPath.'/xml/services8.xml'), '->dump() dumps parameters');

// ->addService()
$t->diag('->addService()');
$container = include $fixturesPath.'/containers/container9.php';
$dumper = new XmlDumper($container);
$t->is($dumper->dump(), str_replace('%path%', $fixturesPath.'/includes', file_get_contents($fixturesPath.'/xml/services9.xml')), '->dump() dumps services');

$dumper = new XmlDumper($container = new Builder());
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
