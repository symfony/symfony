<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Input\ArrayInput;
use Symfony\Components\Console\Input\InputDefinition;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;

$t = new LimeTest(19);

// __construct()
$t->diag('__construct()');
$input = new ArrayInput(array('name' => 'foo'), new InputDefinition(array(new InputArgument('name'))));
$t->is($input->getArgument('name'), 'foo', '->__construct() takes a InputDefinition as an argument');

// ->getOption() ->setOption() ->getOptions()
$t->diag('->getOption() ->setOption() ->getOptions()');
$input = new ArrayInput(array('--name' => 'foo'), new InputDefinition(array(new InputOption('name'))));
$t->is($input->getOption('name'), 'foo', '->getOption() returns the value for the given option');

$input->setOption('name', 'bar');
$t->is($input->getOption('name'), 'bar', '->setOption() sets the value for a given option');
$t->is($input->getOptions(), array('name' => 'bar'), '->getOptions() returns all option values');

$input = new ArrayInput(array('--name' => 'foo'), new InputDefinition(array(new InputOption('name'), new InputOption('bar', '', InputOption::PARAMETER_OPTIONAL, '', 'default'))));
$t->is($input->getOption('bar'), 'default', '->getOption() returns the default value for optional options');
$t->is($input->getOptions(), array('name' => 'foo', 'bar' => 'default'), '->getOptions() returns all option values, even optional ones');

try
{
  $input->setOption('foo', 'bar');
  $t->fail('->setOption() throws a \InvalidArgumentException if the option does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->setOption() throws a \InvalidArgumentException if the option does not exist');
}

try
{
  $input->getOption('foo');
  $t->fail('->getOption() throws a \InvalidArgumentException if the option does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->getOption() throws a \InvalidArgumentException if the option does not exist');
}

// ->getArgument() ->setArgument() ->getArguments()
$t->diag('->getArgument() ->setArgument() ->getArguments()');
$input = new ArrayInput(array('name' => 'foo'), new InputDefinition(array(new InputArgument('name'))));
$t->is($input->getArgument('name'), 'foo', '->getArgument() returns the value for the given argument');

$input->setArgument('name', 'bar');
$t->is($input->getArgument('name'), 'bar', '->setArgument() sets the value for a given argument');
$t->is($input->getArguments(), array('name' => 'bar'), '->getArguments() returns all argument values');

$input = new ArrayInput(array('name' => 'foo'), new InputDefinition(array(new InputArgument('name'), new InputArgument('bar', InputArgument::OPTIONAL, '', 'default'))));
$t->is($input->getArgument('bar'), 'default', '->getArgument() returns the default value for optional arguments');
$t->is($input->getArguments(), array('name' => 'foo', 'bar' => 'default'), '->getArguments() returns all argument values, even optional ones');

try
{
  $input->setArgument('foo', 'bar');
  $t->fail('->setArgument() throws a \InvalidArgumentException if the argument does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->setArgument() throws a \InvalidArgumentException if the argument does not exist');
}

try
{
  $input->getArgument('foo');
  $t->fail('->getArgument() throws a \InvalidArgumentException if the argument does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('->getArgument() throws a \InvalidArgumentException if the argument does not exist');
}

// ->validate()
$t->diag('->validate()');
$input = new ArrayInput(array());
$input->bind(new InputDefinition(array(new InputArgument('name', InputArgument::REQUIRED))));

try
{
  $input->validate();
  $t->fail('->validate() throws a \RuntimeException if not enough arguments are given');
}
catch (\RuntimeException $e)
{
  $t->pass('->validate() throws a \RuntimeException if not enough arguments are given');
}

$input = new ArrayInput(array('name' => 'foo'));
$input->bind(new InputDefinition(array(new InputArgument('name', InputArgument::REQUIRED))));

try
{
  $input->validate();
  $t->pass('->validate() does not throw a \RuntimeException if enough arguments are given');
}
catch (\RuntimeException $e)
{
  $t->fail('->validate() does not throw a \RuntimeException if enough arguments are given');
}

// ->setInteractive() ->isInteractive()
$t->diag('->setInteractive() ->isInteractive()');
$input = new ArrayInput(array());
$t->ok($input->isInteractive(), '->isInteractive() returns whether the input should be interactive or not');
$input->setInteractive(false);
$t->ok(!$input->isInteractive(), '->setInteractive() changes the interactive flag');
