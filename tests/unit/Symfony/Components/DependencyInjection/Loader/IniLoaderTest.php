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
use Symfony\Components\DependencyInjection\Loader\IniFileLoader;

$t = new LimeTest(3);

$fixturesPath = realpath(__DIR__.'/../../../../../fixtures/Symfony/Components/DependencyInjection/');

$loader = new IniFileLoader($fixturesPath.'/ini');
$config = $loader->load('parameters.ini');
$t->is($config->getParameters(), array('foo' => 'bar', 'bar' => '%foo%'), '->load() takes a single file name as its first argument');

try
{
  $loader->load('foo.ini');
  $t->fail('->load() throws an InvalidArgumentException if the loaded file does not exist');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if the loaded file does not exist');
}

try
{
  @$loader->load('nonvalid.ini');
  $t->fail('->load() throws an InvalidArgumentException if the loaded file is not parseable');
}
catch (InvalidArgumentException $e)
{
  $t->pass('->load() throws an InvalidArgumentException if the loaded file is not parseable');
}
