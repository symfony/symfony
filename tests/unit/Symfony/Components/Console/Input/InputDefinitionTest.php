<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Input\InputDefinition;
use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Exception;

$fixtures = __DIR__.'/../../../../../fixtures/Symfony/Components/Console';

$t = new LimeTest(51);

$foo = new InputArgument('foo');
$bar = new InputArgument('bar');
$foo1 = new InputArgument('foo');
$foo2 = new InputArgument('foo2', InputArgument::REQUIRED);

// __construct()
$t->diag('__construct()');
$definition = new InputDefinition();
$t->is($definition->getArguments(), array(), '__construct() creates a new InputDefinition object');

$definition = new InputDefinition(array($foo, $bar));
$t->is($definition->getArguments(), array('foo' => $foo, 'bar' => $bar), '__construct() takes an array of InputArgument objects as its first argument');

// ->setArguments()
$t->diag('->setArguments()');
$definition = new InputDefinition();
$definition->setArguments(array($foo));
$t->is($definition->getArguments(), array('foo' => $foo), '->setArguments() sets the array of InputArgument objects');
$definition->setArguments(array($bar));

$t->is($definition->getArguments(), array('bar' => $bar), '->setArguments() clears all InputArgument objects');

// ->addArguments()
$t->diag('->addArguments()');
$definition = new InputDefinition();
$definition->addArguments(array($foo));
$t->is($definition->getArguments(), array('foo' => $foo), '->addArguments() adds an array of InputArgument objects');
$definition->addArguments(array($bar));
$t->is($definition->getArguments(), array('foo' => $foo, 'bar' => $bar), '->addArguments() does not clear existing InputArgument objects');

// ->addArgument()
$t->diag('->addArgument()');
$definition = new InputDefinition();
$definition->addArgument($foo);
$t->is($definition->getArguments(), array('foo' => $foo), '->addArgument() adds a InputArgument object');
$definition->addArgument($bar);
$t->is($definition->getArguments(), array('foo' => $foo, 'bar' => $bar), '->addArgument() adds a InputArgument object');

// arguments must have different names
try
{
  $definition->addArgument($foo1);
  $t->fail('->addArgument() throws a Exception if another argument is already registered with the same name');
}
catch (\Exception $e)
{
  $t->pass('->addArgument() throws a Exception if another argument is already registered with the same name');
}

// cannot add a parameter after an array parameter
$definition->addArgument(new InputArgument('fooarray', InputArgument::IS_ARRAY));
try
{
  $definition->addArgument(new InputArgument('anotherbar'));
  $t->fail('->addArgument() throws a Exception if there is an array parameter already registered');
}
catch (\Exception $e)
{
  $t->pass('->addArgument() throws a Exception if there is an array parameter already registered');
}

// cannot add a required argument after an optional one
$definition = new InputDefinition();
$definition->addArgument($foo);
try
{
  $definition->addArgument($foo2);
  $t->fail('->addArgument() throws an exception if you try to add a required argument after an optional one');
}
catch (\Exception $e)
{
  $t->pass('->addArgument() throws an exception if you try to add a required argument after an optional one');
}

// ->getArgument()
$t->diag('->getArgument()');
$definition = new InputDefinition();
$definition->addArguments(array($foo));
$t->is($definition->getArgument('foo'), $foo, '->getArgument() returns a InputArgument by its name');
try
{
  $definition->getArgument('bar');
  $t->fail('->getArgument() throws an exception if the InputArgument name does not exist');
}
catch (\Exception $e)
{
  $t->pass('->getArgument() throws an exception if the InputArgument name does not exist');
}

// ->hasArgument()
$t->diag('->hasArgument()');
$definition = new InputDefinition();
$definition->addArguments(array($foo));
$t->is($definition->hasArgument('foo'), true, '->hasArgument() returns true if a InputArgument exists for the given name');
$t->is($definition->hasArgument('bar'), false, '->hasArgument() returns false if a InputArgument exists for the given name');

// ->getArgumentRequiredCount()
$t->diag('->getArgumentRequiredCount()');
$definition = new InputDefinition();
$definition->addArgument($foo2);
$t->is($definition->getArgumentRequiredCount(), 1, '->getArgumentRequiredCount() returns the number of required arguments');
$definition->addArgument($foo);
$t->is($definition->getArgumentRequiredCount(), 1, '->getArgumentRequiredCount() returns the number of required arguments');

// ->getArgumentCount()
$t->diag('->getArgumentCount()');
$definition = new InputDefinition();
$definition->addArgument($foo2);
$t->is($definition->getArgumentCount(), 1, '->getArgumentCount() returns the number of arguments');
$definition->addArgument($foo);
$t->is($definition->getArgumentCount(), 2, '->getArgumentCount() returns the number of arguments');

// ->getArgumentDefaults()
$t->diag('->getArgumentDefaults()');
$definition = new InputDefinition(array(
  new InputArgument('foo1', InputArgument::OPTIONAL),
  new InputArgument('foo2', InputArgument::OPTIONAL, '', 'default'),
  new InputArgument('foo3', InputArgument::OPTIONAL | InputArgument::IS_ARRAY),
//  new InputArgument('foo4', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', array(1, 2)),
));
$t->is($definition->getArgumentDefaults(), array('foo1' => null, 'foo2' => 'default', 'foo3' => array()), '->getArgumentDefaults() return the default values for each argument');

$definition = new InputDefinition(array(
  new InputArgument('foo4', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, '', array(1, 2)),
));
$t->is($definition->getArgumentDefaults(), array('foo4' => array(1, 2)), '->getArgumentDefaults() return the default values for each argument');

$foo = new InputOption('foo', 'f');
$bar = new InputOption('bar', 'b');
$foo1 = new InputOption('fooBis', 'f');
$foo2 = new InputOption('foo', 'p');

// __construct()
$t->diag('__construct()');
$definition = new InputDefinition();
$t->is($definition->getOptions(), array(), '__construct() creates a new InputDefinition object');

$definition = new InputDefinition(array($foo, $bar));
$t->is($definition->getOptions(), array('foo' => $foo, 'bar' => $bar), '__construct() takes an array of InputOption objects as its first argument');

// ->setOptions()
$t->diag('->setOptions()');
$definition = new InputDefinition(array($foo));
$t->is($definition->getOptions(), array('foo' => $foo), '->setOptions() sets the array of InputOption objects');
$definition->setOptions(array($bar));
$t->is($definition->getOptions(), array('bar' => $bar), '->setOptions() clears all InputOption objects');
try
{
  $definition->getOptionForShortcut('f');
  $t->fail('->setOptions() clears all InputOption objects');
}
catch (\Exception $e)
{
  $t->pass('->setOptions() clears all InputOption objects');
}

// ->addOptions()
$t->diag('->addOptions()');
$definition = new InputDefinition(array($foo));
$t->is($definition->getOptions(), array('foo' => $foo), '->addOptions() adds an array of InputOption objects');
$definition->addOptions(array($bar));
$t->is($definition->getOptions(), array('foo' => $foo, 'bar' => $bar), '->addOptions() does not clear existing InputOption objects');

// ->addOption()
$t->diag('->addOption()');
$definition = new InputDefinition();
$definition->addOption($foo);
$t->is($definition->getOptions(), array('foo' => $foo), '->addOption() adds a InputOption object');
$definition->addOption($bar);
$t->is($definition->getOptions(), array('foo' => $foo, 'bar' => $bar), '->addOption() adds a InputOption object');
try
{
  $definition->addOption($foo2);
  $t->fail('->addOption() throws a Exception if the another option is already registered with the same name');
}
catch (\Exception $e)
{
  $t->pass('->addOption() throws a Exception if the another option is already registered with the same name');
}
try
{
  $definition->addOption($foo1);
  $t->fail('->addOption() throws a Exception if the another option is already registered with the same shortcut');
}
catch (\Exception $e)
{
  $t->pass('->addOption() throws a Exception if the another option is already registered with the same shortcut');
}

// ->getOption()
$t->diag('->getOption()');
$definition = new InputDefinition(array($foo));
$t->is($definition->getOption('foo'), $foo, '->getOption() returns a InputOption by its name');
try
{
  $definition->getOption('bar');
  $t->fail('->getOption() throws an exception if the option name does not exist');
}
catch (\Exception $e)
{
  $t->pass('->getOption() throws an exception if the option name does not exist');
}

// ->hasOption()
$t->diag('->hasOption()');
$definition = new InputDefinition(array($foo));
$t->is($definition->hasOption('foo'), true, '->hasOption() returns true if a InputOption exists for the given name');
$t->is($definition->hasOption('bar'), false, '->hasOption() returns false if a InputOption exists for the given name');

// ->hasShortcut()
$t->diag('->hasShortcut()');
$definition = new InputDefinition(array($foo));
$t->is($definition->hasShortcut('f'), true, '->hasShortcut() returns true if a InputOption exists for the given shortcut');
$t->is($definition->hasShortcut('b'), false, '->hasShortcut() returns false if a InputOption exists for the given shortcut');

// ->getOptionForShortcut()
$t->diag('->getOptionForShortcut()');
$definition = new InputDefinition(array($foo));
$t->is($definition->getOptionForShortcut('f'), $foo, '->getOptionForShortcut() returns a InputOption by its shortcut');
try
{
  $definition->getOptionForShortcut('l');
  $t->fail('->getOption() throws an exception if the shortcut does not exist');
}
catch (\Exception $e)
{
  $t->pass('->getOption() throws an exception if the shortcut does not exist');
}

// ->getOptionDefaults()
$t->diag('->getOptionDefaults()');
$definition = new InputDefinition(array(
  new InputOption('foo1', null, InputOption::PARAMETER_NONE),
  new InputOption('foo2', null, InputOption::PARAMETER_REQUIRED),
  new InputOption('foo3', null, InputOption::PARAMETER_REQUIRED, '', 'default'),
  new InputOption('foo4', null, InputOption::PARAMETER_OPTIONAL),
  new InputOption('foo5', null, InputOption::PARAMETER_OPTIONAL, '', 'default'),
  new InputOption('foo6', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY),
  new InputOption('foo7', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY, '', array(1, 2)),
));
$defaults = array(
  'foo1' => null,
  'foo2' => null,
  'foo3' => 'default',
  'foo4' => null,
  'foo5' => 'default',
  'foo6' => array(),
  'foo7' => array(1, 2),
);
$t->is($definition->getOptionDefaults(), $defaults, '->getOptionDefaults() returns the default values for all options');

// ->getSynopsis()
$t->diag('->getSynopsis()');
$definition = new InputDefinition(array(new InputOption('foo')));
$t->is($definition->getSynopsis(), '[--foo]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new InputDefinition(array(new InputOption('foo', 'f')));
$t->is($definition->getSynopsis(), '[-f|--foo]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED)));
$t->is($definition->getSynopsis(), '[-f|--foo="..."]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new InputDefinition(array(new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL)));
$t->is($definition->getSynopsis(), '[-f|--foo[="..."]]', '->getSynopsis() returns a synopsis of arguments and options');

$definition = new InputDefinition(array(new InputArgument('foo')));
$t->is($definition->getSynopsis(), '[foo]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new InputDefinition(array(new InputArgument('foo', InputArgument::REQUIRED)));
$t->is($definition->getSynopsis(), 'foo', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new InputDefinition(array(new InputArgument('foo', InputArgument::IS_ARRAY)));
$t->is($definition->getSynopsis(), '[foo1] ... [fooN]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new InputDefinition(array(new InputArgument('foo', InputArgument::REQUIRED | InputArgument::IS_ARRAY)));
$t->is($definition->getSynopsis(), 'foo1 ... [fooN]', '->getSynopsis() returns a synopsis of arguments and options');

// ->asText()
$t->diag('->asText()');
$definition = new InputDefinition(array(
  new InputArgument('foo', InputArgument::OPTIONAL, 'The bar argument'),
  new InputArgument('bar', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The foo argument', array('bar')),
  new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED, 'The foo option'),
  new InputOption('bar', 'b', InputOption::PARAMETER_OPTIONAL, 'The foo option', 'bar'),
));
$t->is($definition->asText(), file_get_contents($fixtures.'/definition_astext.txt'), '->asText() returns a textual representation of the InputDefinition');

// ->asXml()
$t->diag('->asXml()');
$t->is($definition->asXml(), file_get_contents($fixtures.'/definition_asxml.txt'), '->asText() returns a textual representation of the InputDefinition');
