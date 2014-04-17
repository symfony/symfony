--TEST--
EventDispatcher listeners API()
--SKIPIF--
<?php
    if (!extension_loaded("symfony_eventdispatcher")) die("skip symfony_eventdispatcher is not loaded");
?>
--FILE--
<?php

$d = new Symfony\Component\EventDispatcher\EventDispatcher;
$d->addListener('test-event', $foo = 'foo', 10);

$d->addListener('test-event2', 'bar', 10);
$d->addListener('test-event2', 'baz', 12);



var_dump($d->getListeners('test-event2'));
var_dump($d->getListeners('test-event-nonexistent'));
var_dump($d->getListeners());

var_dump($d->hasListeners('test-event2'));
var_dump($d->hasListeners('test-event-nonexistent'));
var_dump($d->hasListeners());

$d->removeListener('test-event', $foo);
var_dump($d->hasListeners('test-event'));

$d->removeListener('test-event2', 'baz');
var_dump(count($d->getListeners('test-event2')));
?>
--EXPECTF--
array(2) {
  [0]=>
  string(3) "baz"
  [1]=>
  string(3) "bar"
}
array(0) {
}
array(2) {
  ["test-event"]=>
  array(1) {
    [0]=>
    string(3) "foo"
  }
  ["test-event2"]=>
  array(2) {
    [0]=>
    string(3) "baz"
    [1]=>
    string(3) "bar"
  }
}
bool(true)
bool(false)
bool(true)
bool(false)
int(1)