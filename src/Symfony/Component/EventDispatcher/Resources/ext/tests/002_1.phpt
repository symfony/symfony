--TEST--
EventDispatcher dispatch() propagation stopped
--SKIPIF--
<?php
    if (!extension_loaded("symfony_eventdispatcher")) die("skip symfony_eventdispatcher is not loaded");
?>
--FILE--
<?php
function foo2(Symfony\Component\EventDispatcher\Event $e) { $e->stopPropagation(); }
function foo1() { echo "this should appear"; }
function foo3() { echo "this should not appear"; }

$d = new Symfony\Component\EventDispatcher\EventDispatcher;
$d->addListener('test-event', 'foo1', 100);
$d->addListener('test-event', 'foo2', 50);
$d->addListener('test-event', 'foo3', 1);

$d->dispatch('test-event');
?>
--EXPECTF--
this should appear