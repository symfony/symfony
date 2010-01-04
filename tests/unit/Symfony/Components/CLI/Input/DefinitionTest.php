<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\CLI\Input\Definition;
use Symfony\Components\CLI\Input\Argument;
use Symfony\Components\CLI\Input\Option;
use Symfony\Components\CLI\Exception;

$fixtures = __DIR__.'/../../../../../fixtures/Symfony/Components/CLI';

$t = new LimeTest(51);

$foo = new Argument('foo');
$bar = new Argument('bar');
$foo1 = new Argument('foo');
$foo2 = new Argument('foo2', Argument::REQUIRED);

// __construct()
$t->diag('__construct()');
$definition = new Definition();
$t->is($definition->getArguments(), array(), '__construct() creates a new Definition object');

$definition = new Definition(array($foo, $bar));
$t->is($definition->getArguments(), array('foo' => $foo, 'bar' => $bar), '__construct() takes an array of Argument objects as its first argument');

// ->setArguments()
$t->diag('->setArguments()');
$definition = new Definition();
$definition->setArguments(array($foo));
$t->is($definition->getArguments(), array('foo' => $foo), '->setArguments() sets the array of Argument objects');
$definition->setArguments(array($bar));

$t->is($definition->getArguments(), array('bar' => $bar), '->setArguments() clears all Argument objects');

// ->addArguments()
$t->diag('->addArguments()');
$definition = new Definition();
$definition->addArguments(array($foo));
$t->is($definition->getArguments(), array('foo' => $foo), '->addArguments() adds an array of Argument objects');
$definition->addArguments(array($bar));
$t->is($definition->getArguments(), array('foo' => $foo, 'bar' => $bar), '->addArguments() does not clear existing Argument objects');

// ->addArgument()
$t->diag('->addArgument()');
$definition = new Definition();
$definition->addArgument($foo);
$t->is($definition->getArguments(), array('foo' => $foo), '->addArgument() adds a Argument object');
$definition->addArgument($bar);
$t->is($definition->getArguments(), array('foo' => $foo, 'bar' => $bar), '->addArgument() adds a Argument object');

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
$definition->addArgument(new Argument('fooarray', Argument::IS_ARRAY));
try
{
  $definition->addArgument(new Argument('anotherbar'));
  $t->fail('->addArgument() throws a Exception if there is an array parameter already registered');
}
catch (\Exception $e)
{
  $t->pass('->addArgument() throws a Exception if there is an array parameter already registered');
}

// cannot add a required argument after an optional one
$definition = new Definition();
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
$definition = new Definition();
$definition->addArguments(array($foo));
$t->is($definition->getArgument('foo'), $foo, '->getArgument() returns a Argument by its name');
try
{
  $definition->getArgument('bar');
  $t->fail('->getArgument() throws an exception if the Argument name does not exist');
}
catch (\Exception $e)
{
  $t->pass('->getArgument() throws an exception if the Argument name does not exist');
}

// ->hasArgument()
$t->diag('->hasArgument()');
$definition = new Definition();
$definition->addArguments(array($foo));
$t->is($definition->hasArgument('foo'), true, '->hasArgument() returns true if a Argument exists for the given name');
$t->is($definition->hasArgument('bar'), false, '->hasArgument() returns false if a Argument exists for the given name');

// ->getArgumentRequiredCount()
$t->diag('->getArgumentRequiredCount()');
$definition = new Definition();
$definition->addArgument($foo2);
$t->is($definition->getArgumentRequiredCount(), 1, '->getArgumentRequiredCount() returns the number of required arguments');
$definition->addArgument($foo);
$t->is($definition->getArgumentRequiredCount(), 1, '->getArgumentRequiredCount() returns the number of required arguments');

// ->getArgumentCount()
$t->diag('->getArgumentCount()');
$definition = new Definition();
$definition->addArgument($foo2);
$t->is($definition->getArgumentCount(), 1, '->getArgumentCount() returns the number of arguments');
$definition->addArgument($foo);
$t->is($definition->getArgumentCount(), 2, '->getArgumentCount() returns the number of arguments');

// ->getArgumentDefaults()
$t->diag('->getArgumentDefaults()');
$definition = new Definition(array(
  new Argument('foo1', Argument::OPTIONAL),
  new Argument('foo2', Argument::OPTIONAL, '', 'default'),
  new Argument('foo3', Argument::OPTIONAL | Argument::IS_ARRAY),
//  new Argument('foo4', Argument::OPTIONAL | Argument::IS_ARRAY, '', array(1, 2)),
));
$t->is($definition->getArgumentDefaults(), array('foo1' => null, 'foo2' => 'default', 'foo3' => array()), '->getArgumentDefaults() return the default values for each argument');

$definition = new Definition(array(
  new Argument('foo4', Argument::OPTIONAL | Argument::IS_ARRAY, '', array(1, 2)),
));
$t->is($definition->getArgumentDefaults(), array('foo4' => array(1, 2)), '->getArgumentDefaults() return the default values for each argument');

$foo = new Option('foo', 'f');
$bar = new Option('bar', 'b');
$foo1 = new Option('fooBis', 'f');
$foo2 = new Option('foo', 'p');

// __construct()
$t->diag('__construct()');
$definition = new Definition();
$t->is($definition->getOptions(), array(), '__construct() creates a new Definition object');

$definition = new Definition(array($foo, $bar));
$t->is($definition->getOptions(), array('foo' => $foo, 'bar' => $bar), '__construct() takes an array of Option objects as its first argument');

// ->setOptions()
$t->diag('->setOptions()');
$definition = new Definition(array($foo));
$t->is($definition->getOptions(), array('foo' => $foo), '->setOptions() sets the array of Option objects');
$definition->setOptions(array($bar));
$t->is($definition->getOptions(), array('bar' => $bar), '->setOptions() clears all Option objects');
try
{
  $definition->getOptionForShortcut('f');
  $t->fail('->setOptions() clears all Option objects');
}
catch (\Exception $e)
{
  $t->pass('->setOptions() clears all Option objects');
}

// ->addOptions()
$t->diag('->addOptions()');
$definition = new Definition(array($foo));
$t->is($definition->getOptions(), array('foo' => $foo), '->addOptions() adds an array of Option objects');
$definition->addOptions(array($bar));
$t->is($definition->getOptions(), array('foo' => $foo, 'bar' => $bar), '->addOptions() does not clear existing Option objects');

// ->addOption()
$t->diag('->addOption()');
$definition = new Definition();
$definition->addOption($foo);
$t->is($definition->getOptions(), array('foo' => $foo), '->addOption() adds a Option object');
$definition->addOption($bar);
$t->is($definition->getOptions(), array('foo' => $foo, 'bar' => $bar), '->addOption() adds a Option object');
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
$definition = new Definition(array($foo));
$t->is($definition->getOption('foo'), $foo, '->getOption() returns a Option by its name');
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
$definition = new Definition(array($foo));
$t->is($definition->hasOption('foo'), true, '->hasOption() returns true if a Option exists for the given name');
$t->is($definition->hasOption('bar'), false, '->hasOption() returns false if a Option exists for the given name');

// ->hasShortcut()
$t->diag('->hasShortcut()');
$definition = new Definition(array($foo));
$t->is($definition->hasShortcut('f'), true, '->hasShortcut() returns true if a Option exists for the given shortcut');
$t->is($definition->hasShortcut('b'), false, '->hasShortcut() returns false if a Option exists for the given shortcut');

// ->getOptionForShortcut()
$t->diag('->getOptionForShortcut()');
$definition = new Definition(array($foo));
$t->is($definition->getOptionForShortcut('f'), $foo, '->getOptionForShortcut() returns a Option by its shortcut');
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
$definition = new Definition(array(
  new Option('foo1', null, Option::PARAMETER_NONE),
  new Option('foo2', null, Option::PARAMETER_REQUIRED),
  new Option('foo3', null, Option::PARAMETER_REQUIRED, '', 'default'),
  new Option('foo4', null, Option::PARAMETER_OPTIONAL),
  new Option('foo5', null, Option::PARAMETER_OPTIONAL, '', 'default'),
  new Option('foo6', null, Option::PARAMETER_OPTIONAL | Option::PARAMETER_IS_ARRAY),
  new Option('foo7', null, Option::PARAMETER_OPTIONAL | Option::PARAMETER_IS_ARRAY, '', array(1, 2)),
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
$definition = new Definition(array(new Option('foo')));
$t->is($definition->getSynopsis(), '[--foo]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new Definition(array(new Option('foo', 'f')));
$t->is($definition->getSynopsis(), '[-f|--foo]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new Definition(array(new Option('foo', 'f', Option::PARAMETER_REQUIRED)));
$t->is($definition->getSynopsis(), '[-f|--foo="..."]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new Definition(array(new Option('foo', 'f', Option::PARAMETER_OPTIONAL)));
$t->is($definition->getSynopsis(), '[-f|--foo[="..."]]', '->getSynopsis() returns a synopsis of arguments and options');

$definition = new Definition(array(new Argument('foo')));
$t->is($definition->getSynopsis(), '[foo]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new Definition(array(new Argument('foo', Argument::REQUIRED)));
$t->is($definition->getSynopsis(), 'foo', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new Definition(array(new Argument('foo', Argument::IS_ARRAY)));
$t->is($definition->getSynopsis(), '[foo1] ... [fooN]', '->getSynopsis() returns a synopsis of arguments and options');
$definition = new Definition(array(new Argument('foo', Argument::REQUIRED | Argument::IS_ARRAY)));
$t->is($definition->getSynopsis(), 'foo1 ... [fooN]', '->getSynopsis() returns a synopsis of arguments and options');

// ->asText()
$t->diag('->asText()');
$definition = new Definition(array(
  new Argument('foo', Argument::OPTIONAL, 'The bar argument'),
  new Argument('bar', Argument::OPTIONAL | Argument::IS_ARRAY, 'The foo argument', array('bar')),
  new Option('foo', 'f', Option::PARAMETER_REQUIRED, 'The foo option'),
  new Option('bar', 'b', Option::PARAMETER_OPTIONAL, 'The foo option', 'bar'),
));
$t->is($definition->asText(), file_get_contents($fixtures.'/definition_astext.txt'), '->asText() returns a textual representation of the Definition');

// ->asXml()
$t->diag('->asXml()');
$t->is($definition->asXml(), file_get_contents($fixtures.'/definition_asxml.txt'), '->asText() returns a textual representation of the Definition');
