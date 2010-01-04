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
use Symfony\Components\DependencyInjection\Dumper\GraphvizDumper;

$t = new LimeTest(4);

$fixturesPath = __DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/';

// ->dump()
$t->diag('->dump()');
$dumper = new GraphvizDumper($container = new Builder());

$t->is($dumper->dump(), file_get_contents($fixturesPath.'/graphviz/services1.dot'), '->dump() dumps an empty container as an empty dot file');

$container = new Builder();
$dumper = new GraphvizDumper($container);

$container = include $fixturesPath.'/containers/container9.php';
$dumper = new GraphvizDumper($container);
$t->is($dumper->dump(), str_replace('%path%', __DIR__, file_get_contents($fixturesPath.'/graphviz/services9.dot')), '->dump() dumps services');

$container = include $fixturesPath.'/containers/container10.php';
$dumper = new GraphvizDumper($container);
$t->is($dumper->dump(), str_replace('%path%', __DIR__, file_get_contents($fixturesPath.'/graphviz/services10.dot')), '->dump() dumps services');

$container = include $fixturesPath.'/containers/container10.php';
$dumper = new GraphvizDumper($container);
$t->is($dumper->dump(array(
  'graph' => array('ratio' => 'normal'),
  'node'  => array('fontsize' => 13, 'fontname' => 'Verdana', 'shape' => 'square'),
  'edge'  => array('fontsize' => 12, 'fontname' => 'Verdana', 'color' => 'white', 'arrowhead' => 'closed', 'arrowsize' => 1),
  'node.instance' => array('fillcolor' => 'green', 'style' => 'empty'),
  'node.definition' => array('fillcolor' => 'grey'),
  'node.missing' => array('fillcolor' => 'red', 'style' => 'empty'),
)), str_replace('%path%', __DIR__, file_get_contents($fixturesPath.'/graphviz/services10-1.dot')), '->dump() dumps services');
