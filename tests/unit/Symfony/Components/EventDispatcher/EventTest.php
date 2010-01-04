<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../../../bootstrap.php';

use Symfony\Components\EventDispatcher\Event;

$t = new LimeTest(11);

$subject = new stdClass();
$parameters = array('foo' => 'bar');
$event = new Event($subject, 'name', $parameters);

// ->getSubject()
$t->diag('->getSubject()');
$t->is($event->getSubject(), $subject, '->getSubject() returns the event subject');

// ->getName()
$t->diag('->getName()');
$t->is($event->getName(), 'name', '->getName() returns the event name');

// ->getParameters()
$t->diag('->getParameters()');
$t->is($event->getParameters(), $parameters, '->getParameters() returns the event parameters');

// ->getReturnValue() ->setReturnValue()
$t->diag('->getReturnValue() ->setReturnValue()');
$event->setReturnValue('foo');
$t->is($event->getReturnValue(), 'foo', '->getReturnValue() returns the return value of the event');

// ->setProcessed() ->isProcessed()
$t->diag('->setProcessed() ->isProcessed()');
$event->setProcessed(true);
$t->is($event->isProcessed(), true, '->isProcessed() returns true if the event has been processed');
$event->setProcessed(false);
$t->is($event->isProcessed(), false, '->setProcessed() changes the processed status');

// ArrayAccess interface
$t->diag('ArrayAccess interface');
$t->is($event['foo'], 'bar', 'Event implements the ArrayAccess interface');
$event['foo'] = 'foo';
$t->is($event['foo'], 'foo', 'Event implements the ArrayAccess interface');

try
{
  $event['foobar'];
  $t->fail('::offsetGet() throws an \InvalidArgumentException exception when the parameter does not exist');
}
catch (\InvalidArgumentException $e)
{
  $t->pass('::offsetGet() throws an \InvalidArgumentException exception when the parameter does not exist');
}

$t->ok(isset($event['foo']), 'Event implements the ArrayAccess interface');
unset($event['foo']);
$t->ok(!isset($event['foo']), 'Event implements the ArrayAccess interface');
