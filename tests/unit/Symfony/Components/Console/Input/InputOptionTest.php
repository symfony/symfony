<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Exception;

$t = new LimeTest(34);

// __construct()
$t->diag('__construct()');
$option = new InputOption('foo');
$t->is($option->getName(), 'foo', '__construct() takes a name as its first argument');
$option = new InputOption('--foo');
$t->is($option->getName(), 'foo', '__construct() removes the leading -- of the option name');

try
{
  $option = new InputOption('foo', 'f', InputOption::PARAMETER_IS_ARRAY);
  $t->fail('->setDefault() throws an Exception if PARAMETER_IS_ARRAY option is used when an option does not accept a value');
}
catch (\Exception $e)
{
  $t->pass('->setDefault() throws an Exception if PARAMETER_IS_ARRAY option is used when an option does not accept a value');
}

// shortcut argument
$option = new InputOption('foo', 'f');
$t->is($option->getShortcut(), 'f', '__construct() can take a shortcut as its second argument');
$option = new InputOption('foo', '-f');
$t->is($option->getShortcut(), 'f', '__construct() removes the leading - of the shortcut');

// mode argument
$option = new InputOption('foo', 'f');
$t->is($option->acceptParameter(), false, '__construct() gives a "Option::PARAMETER_NONE" mode by default');
$t->is($option->isParameterRequired(), false, '__construct() gives a "Option::PARAMETER_NONE" mode by default');
$t->is($option->isParameterOptional(), false, '__construct() gives a "Option::PARAMETER_NONE" mode by default');

$option = new InputOption('foo', 'f', null);
$t->is($option->acceptParameter(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');
$t->is($option->isParameterRequired(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');
$t->is($option->isParameterOptional(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');

$option = new InputOption('foo', 'f', InputOption::PARAMETER_NONE);
$t->is($option->acceptParameter(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');
$t->is($option->isParameterRequired(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');
$t->is($option->isParameterOptional(), false, '__construct() can take "Option::PARAMETER_NONE" as its mode');

$option = new InputOption('foo', 'f', InputOption::PARAMETER_REQUIRED);
$t->is($option->acceptParameter(), true, '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');
$t->is($option->isParameterRequired(), true, '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');
$t->is($option->isParameterOptional(), false, '__construct() can take "Option::PARAMETER_REQUIRED" as its mode');

$option = new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL);
$t->is($option->acceptParameter(), true, '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');
$t->is($option->isParameterRequired(), false, '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');
$t->is($option->isParameterOptional(), true, '__construct() can take "Option::PARAMETER_OPTIONAL" as its mode');

try
{
  $option = new InputOption('foo', 'f', 'ANOTHER_ONE');
  $t->fail('__construct() throws an Exception if the mode is not valid');
}
catch (\Exception $e)
{
  $t->pass('__construct() throws an Exception if the mode is not valid');
}

// ->isArray()
$t->diag('->isArray()');
$option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
$t->ok($option->isArray(), '->isArray() returns true if the option can be an array');
$option = new InputOption('foo', null, InputOption::PARAMETER_NONE);
$t->ok(!$option->isArray(), '->isArray() returns false if the option can not be an array');

// ->getDescription()
$t->diag('->getDescription()');
$option = new InputOption('foo', 'f', null, 'Some description');
$t->is($option->getDescription(), 'Some description', '->getDescription() returns the description message');

// ->getDefault()
$t->diag('->getDefault()');
$option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL, '', 'default');
$t->is($option->getDefault(), 'default', '->getDefault() returns the default value');

$option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED, '', 'default');
$t->is($option->getDefault(), 'default', '->getDefault() returns the default value');

$option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED);
$t->ok(is_null($option->getDefault()), '->getDefault() returns null if no default value is configured');

$option = new InputOption('foo', null, InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
$t->is($option->getDefault(), array(), '->getDefault() returns an empty array if option is an array');

$option = new InputOption('foo', null, InputOption::PARAMETER_NONE);
$t->ok($option->getDefault() === false, '->getDefault() returns false if the option does not take a parameter');

// ->setDefault()
$t->diag('->setDefault()');
$option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED, '', 'default');
$option->setDefault(null);
$t->ok(is_null($option->getDefault()), '->setDefault() can reset the default value by passing null');
$option->setDefault('another');
$t->is($option->getDefault(), 'another', '->setDefault() changes the default value');

$option = new InputOption('foo', null, InputOption::PARAMETER_REQUIRED | InputOption::PARAMETER_IS_ARRAY);
$option->setDefault(array(1, 2));
$t->is($option->getDefault(), array(1, 2), '->setDefault() changes the default value');

$option = new InputOption('foo', 'f', InputOption::PARAMETER_NONE);
try
{
  $option->setDefault('default');
  $t->fail('->setDefault() throws an Exception if you give a default value for a PARAMETER_NONE option');
}
catch (\Exception $e)
{
  $t->pass('->setDefault() throws an Exception if you give a default value for a PARAMETER_NONE option');
}

$option = new InputOption('foo', 'f', InputOption::PARAMETER_OPTIONAL | InputOption::PARAMETER_IS_ARRAY);
try
{
  $option->setDefault('default');
  $t->fail('->setDefault() throws an Exception if you give a default value which is not an array for a PARAMETER_IS_ARRAY option');
}
catch (\Exception $e)
{
  $t->pass('->setDefault() throws an Exception if you give a default value which is not an array for a PARAMETER_IS_ARRAY option');
}
