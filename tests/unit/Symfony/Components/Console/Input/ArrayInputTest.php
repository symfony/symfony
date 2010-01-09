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

$t = new LimeTest(15);

// ->getFirstArgument()
$t->diag('->getFirstArgument()');
$input = new ArrayInput(array());
$t->is($input->getFirstArgument(), null, '->getFirstArgument() returns null if no argument were passed');
$input = new ArrayInput(array('name' => 'Fabien'));
$t->is($input->getFirstArgument(), 'Fabien', '->getFirstArgument() returns the first passed argument');
$input = new ArrayInput(array('--foo' => 'bar', 'name' => 'Fabien'));
$t->is($input->getFirstArgument(), 'Fabien', '->getFirstArgument() returns the first passed argument');

// ->hasParameterOption()
$t->diag('->hasParameterOption()');
$input = new ArrayInput(array('name' => 'Fabien', '--foo' => 'bar'));
$t->ok($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if an option is present in the passed parameters');
$t->ok(!$input->hasParameterOption('--bar'), '->hasParameterOption() returns false if an option is not present in the passed parameters');

$input = new ArrayInput(array('--foo'));
$t->ok($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if an option is present in the passed parameters');

// ->parse()
$t->diag('->parse()');
$input = new ArrayInput(array('name' => 'foo'), new InputDefinition(array(new InputArgument('name'))));
$t->is($input->getArguments(), array('name' => 'foo'), '->parse() parses required arguments');

try
{
  $input = new ArrayInput(array('foo' => 'foo'), new InputDefinition(array(new InputArgument('name'))));
  $t->fail('->parse() throws an \InvalidArgumentException exception if an invalid argument is passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws an \InvalidArgumentException exception if an invalid argument is passed');
}

$input = new ArrayInput(array('--foo' => 'bar'), new InputDefinition(array(new InputOption('foo'))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses long options');

$input = new ArrayInput(array('--foo' => 'bar'), new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL, '', 'default'))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses long options with a default value');

$input = new ArrayInput(array('--foo' => null), new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL, '', 'default'))));
$t->is($input->getOptions(), array('foo' => 'default'), '->parse() parses long options with a default value');

try
{
  $input = new ArrayInput(array('--foo' => null), new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED))));
  $t->fail('->parse() throws an \InvalidArgumentException exception if a required option is passed without a value');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws an \InvalidArgumentException exception if a required option is passed without a value');
}

try
{
  $input = new ArrayInput(array('--foo' => 'foo'), new InputDefinition());
  $t->fail('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
}

$input = new ArrayInput(array('-f' => 'bar'), new InputDefinition(array(new InputOption('foo', 'f'))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses short options');

try
{
  $input = new ArrayInput(array('-o' => 'foo'), new InputDefinition());
  $t->fail('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
}
