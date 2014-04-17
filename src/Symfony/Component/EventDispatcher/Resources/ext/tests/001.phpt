--TEST--
Event API
--SKIPIF--
<?php
    if (!extension_loaded("symfony_eventdispatcher")) die("skip symfony_eventdispatcher is not loaded");
?>
--FILE--
<?php
$e = new Symfony\Component\EventDispatcher\Event;
$e->setName('foo');
var_dump($e->getName());

$e->setDispatcher(new Symfony\Component\EventDispatcher\EventDispatcher);
var_dump($e->getDispatcher() instanceof Symfony\Component\EventDispatcher\EventDispatcher);

var_dump($e->isPropagationStopped());
$e->stopPropagation();
var_dump($e->isPropagationStopped());
?>
--EXPECTF--
string(3) "foo"
bool(true)
bool(false)
bool(true)