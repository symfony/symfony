<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Input\ArgvInput;
use Symfony\Components\Console\Input\InputDefinition;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;

class TestInput extends ArgvInput
{
  public function getTokens()
  {
    return $this->tokens;
  }
}

$t = new LimeTest(26);

// __construct()
$t->diag('__construct()');
$_SERVER['argv'] = array('cli.php', 'foo');
$input = new TestInput();
$t->is($input->getTokens(), array('foo'), '__construct() automatically get its input from the argv server variable');

// ->parse()
$t->diag('->parse()');
$input = new TestInput(array('cli.php', 'foo'));
$input->bind(new InputDefinition(array(new InputArgument('name'))));
$t->is($input->getArguments(), array('name' => 'foo'), '->parse() parses required arguments');

$input->bind(new InputDefinition(array(new InputArgument('name'))));
$t->is($input->getArguments(), array('name' => 'foo'), '->parse() is stateless');

$input = new TestInput(array('cli.php', '--foo'));
$input->bind(new InputDefinition(array(new InputOption('foo'))));
$t->is($input->getOptions(), array('foo' => true), '->parse() parses long options without parameter');

$input = new TestInput(array('cli.php', '--foo=bar'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses long options with a required parameter (with a = separator)');

$input = new TestInput(array('cli.php', '--foo', 'bar'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses long options with a required parameter (with a space separator)');

try
{
  $input = new TestInput(array('cli.php', '--foo'));
  $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED))));
  $t->fail('->parse() throws a \RuntimeException if no parameter is passed to an option when it is required');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws a \RuntimeException if no parameter is passed to an option when it is required');
}

$input = new TestInput(array('cli.php', '-f'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f'))));
$t->is($input->getOptions(), array('foo' => true), '->parse() parses short options without parameter');

$input = new TestInput(array('cli.php', '-fbar'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses short options with a required parameter (with no separator)');

$input = new TestInput(array('cli.php', '-f', 'bar'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED))));
$t->is($input->getOptions(), array('foo' => 'bar'), '->parse() parses short options with a required parameter (with a space separator)');

$input = new TestInput(array('cli.php', '-f', '-b', 'foo'));
$input->bind(new InputDefinition(array(new InputArgument('name'), new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL), new InputOption('bar', 'b'))));
$t->is($input->getOptions(), array('foo' => null, 'bar' => true), '->parse() parses short options with an optional parameter which is not present');

try
{
  $input = new TestInput(array('cli.php', '-f'));
  $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED))));
  $t->fail('->parse() throws a \RuntimeException if no parameter is passed to an option when it is required');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws a \RuntimeException if no parameter is passed to an option when it is required');
}

try
{
  $input = new TestInput(array('cli.php', '-ffoo'));
  $input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_NONE))));
  $t->fail('->parse() throws a \RuntimeException if a value is passed to an option which does not take one');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws a \RuntimeException if a value is passed to an option which does not take one');
}

try
{
  $input = new TestInput(array('cli.php', 'foo', 'bar'));
  $input->bind(new InputDefinition());
  $t->fail('->parse() throws a \RuntimeException if too many arguments are passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws a \RuntimeException if too many arguments are passed');
}

try
{
  $input = new TestInput(array('cli.php', '--foo'));
  $input->bind(new InputDefinition());
  $t->fail('->parse() throws a \RuntimeException if an unknown long option is passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws a \RuntimeException if an unknown long option is passed');
}

try
{
  $input = new TestInput(array('cli.php', '-f'));
  $input->bind(new InputDefinition());
  $t->fail('->parse() throws a \RuntimeException if an unknown short option is passed');
}
catch (\RuntimeException $e)
{
  $t->pass('->parse() throws a \RuntimeException if an unknown short option is passed');
}

$input = new TestInput(array('cli.php', '-fb'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f'), new InputOption('bar', 'b'))));
$t->is($input->getOptions(), array('foo' => true, 'bar' => true), '->parse() parses short options when they are aggregated as a single one');

$input = new TestInput(array('cli.php', '-fb', 'bar'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f'), new InputOption('bar', 'b', InputOption::PARAMETER_REQUIRED))));
$t->is($input->getOptions(), array('foo' => true, 'bar' => 'bar'), '->parse() parses short options when they are aggregated as a single one and the last one has a required parameter');

$input = new TestInput(array('cli.php', '-fb', 'bar'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f'), new InputOption('bar', 'b', InputOption::PARAMETER_OPTIONAL))));
$t->is($input->getOptions(), array('foo' => true, 'bar' => 'bar'), '->parse() parses short options when they are aggregated as a single one and the last one has an optional parameter');

$input = new TestInput(array('cli.php', '-fbbar'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f'), new InputOption('bar', 'b', InputOption::PARAMETER_OPTIONAL))));
$t->is($input->getOptions(), array('foo' => true, 'bar' => 'bar'), '->parse() parses short options when they are aggregated as a single one and the last one has an optional parameter with no separator');

$input = new TestInput(array('cli.php', '-fbbar'));
$input->bind(new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL), new InputOption('bar', 'b', InputOption::PARAMETER_OPTIONAL))));
$t->is($input->getOptions(), array('foo' => 'bbar', 'bar' => null), '->parse() parses short options when they are aggregated as a single one and one of them takes a parameter');

// ->getFirstArgument()
$t->diag('->getFirstArgument()');
$input = new TestInput(array('cli.php', '-fbbar'));
$t->is($input->getFirstArgument(), '', '->getFirstArgument() returns the first argument from the raw input');

$input = new TestInput(array('cli.php', '-fbbar', 'foo'));
$t->is($input->getFirstArgument(), 'foo', '->getFirstArgument() returns the first argument from the raw input');

// ->hasParameterOption()
$t->diag('->hasParameterOption()');
$input = new TestInput(array('cli.php', '-f', 'foo'));
$t->ok($input->hasParameterOption('-f'), '->hasParameterOption() returns true if the given short option is in the raw input');

$input = new TestInput(array('cli.php', '--foo', 'foo'));
$t->ok($input->hasParameterOption('--foo'), '->hasParameterOption() returns true if the given short option is in the raw input');

$input = new TestInput(array('cli.php', 'foo'));
$t->ok(!$input->hasParameterOption('--foo'), '->hasParameterOption() returns false if the given short option is not in the raw input');
