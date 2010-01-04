<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\CLI\Input\ArrayInput;
use Symfony\Components\CLI\Input\Definition;
use Symfony\Components\CLI\Input\Argument;
use Symfony\Components\CLI\Input\Option;

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
$input = new ArrayInput(array('name' => 'foo'), new Definition(array(new Argument('name'))));
$t->is($input->getArguments(), array('name' => 'foo'), '->parse() parses required arguments');

try
{
  $input = new ArrayInput(array('foo' => 'foo'), new Definition(array(new Argument('name'))));
  $t->fail('->parse() throws an \InvalidArgumentException exception if an invalid argument is passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws an \InvalidArgumentException exception if an invalid argument is passed');
}

$input = new ArrayInput(array('--foo' => 'bar'), new Definition(array(new Option('foo'))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses long options');

$input = new ArrayInput(array('--foo' => 'bar'), new Definition(array(new Option('foo', 'f', Option::PARAMETER_OPTIONAL, '', 'default'))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses long options with a default value');

$input = new ArrayInput(array('--foo' => null), new Definition(array(new Option('foo', 'f', Option::PARAMETER_OPTIONAL, '', 'default'))));
$t->is($input->getOptions(), array('foo' => 'default'), '->parse() parses long options with a default value');

try
{
  $input = new ArrayInput(array('--foo' => null), new Definition(array(new Option('foo', 'f', Option::PARAMETER_REQUIRED))));
  $t->fail('->parse() throws an \InvalidArgumentException exception if a required option is passed without a value');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws an \InvalidArgumentException exception if a required option is passed without a value');
}

try
{
  $input = new ArrayInput(array('--foo' => 'foo'), new Definition());
  $t->fail('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
}

$input = new ArrayInput(array('-f' => 'bar'), new Definition(array(new Option('foo', 'f'))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses short options');

try
{
  $input = new ArrayInput(array('-o' => 'foo'), new Definition());
  $t->fail('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws an \InvalidArgumentException exception if an invalid option is passed');
}
