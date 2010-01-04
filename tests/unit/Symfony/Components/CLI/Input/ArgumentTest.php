<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../../bootstrap.php';

use Symfony\Components\CLI\Input\Argument;
use Symfony\Components\CLI\Exception;

$t = new LimeTest(16);

// __construct()
$t->diag('__construct()');
$argument = new Argument('foo');
$t->is($argument->getName(), 'foo', '__construct() takes a name as its first argument');

// mode argument
$argument = new Argument('foo');
$t->is($argument->isRequired(), false, '__construct() gives a "Argument::OPTIONAL" mode by default');

$argument = new Argument('foo', null);
$t->is($argument->isRequired(), false, '__construct() can take "Argument::OPTIONAL" as its mode');

$argument = new Argument('foo', Argument::OPTIONAL);
$t->is($argument->isRequired(), false, '__construct() can take "Argument::PARAMETER_OPTIONAL" as its mode');

$argument = new Argument('foo', Argument::REQUIRED);
$t->is($argument->isRequired(), true, '__construct() can take "Argument::PARAMETER_REQUIRED" as its mode');

try
{
  $argument = new Argument('foo', 'ANOTHER_ONE');
  $t->fail('__construct() throws an Exception if the mode is not valid');
}
catch (\Exception $e)
{
  $t->pass('__construct() throws an Exception if the mode is not valid');
}

// ->isArray()
$t->diag('->isArray()');
$argument = new Argument('foo', Argument::IS_ARRAY);
$t->ok($argument->isArray(), '->isArray() returns true if the argument can be an array');
$argument = new Argument('foo', Argument::OPTIONAL | Argument::IS_ARRAY);
$t->ok($argument->isArray(), '->isArray() returns true if the argument can be an array');
$argument = new Argument('foo', Argument::OPTIONAL);
$t->ok(!$argument->isArray(), '->isArray() returns false if the argument can not be an array');

// ->getDescription()
$t->diag('->getDescription()');
$argument = new Argument('foo', null, 'Some description');
$t->is($argument->getDescription(), 'Some description', '->getDescription() return the message description');

// ->getDefault()
$t->diag('->getDefault()');
$argument = new Argument('foo', Argument::OPTIONAL, '', 'default');
$t->is($argument->getDefault(), 'default', '->getDefault() return the default value');

// ->setDefault()
$t->diag('->setDefault()');
$argument = new Argument('foo', Argument::OPTIONAL, '', 'default');
$argument->setDefault(null);
$t->ok(is_null($argument->getDefault()), '->setDefault() can reset the default value by passing null');
$argument->setDefault('another');
$t->is($argument->getDefault(), 'another', '->setDefault() changes the default value');

$argument = new Argument('foo', Argument::OPTIONAL | Argument::IS_ARRAY);
$argument->setDefault(array(1, 2));
$t->is($argument->getDefault(), array(1, 2), '->setDefault() changes the default value');

try
{
  $argument = new Argument('foo', Argument::REQUIRED);
  $argument->setDefault('default');
  $t->fail('->setDefault() throws an Exception if you give a default value for a required argument');
}
catch (\Exception $e)
{
  $t->pass('->setDefault() throws an Exception if you give a default value for a required argument');
}

try
{
  $argument = new Argument('foo', Argument::IS_ARRAY);
  $argument->setDefault('default');
  $t->fail('->setDefault() throws an Exception if you give a default value which is not an array for a IS_ARRAY option');
}
catch (\Exception $e)
{
  $t->pass('->setDefault() throws an Exception if you give a default value which is not an array for a IS_ARRAY option');
}
