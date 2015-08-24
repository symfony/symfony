--TEST--
EventDispatcher sorting listeners
--SKIPIF--
<?php
    if (!extension_loaded("symfony_eventdispatcher")) die("skip symfony_eventdispatcher is not loaded");
?>
--FILE--
<?php
class MyListener1 { public function __invoke() { } }
class MyListener2 { public function __invoke() { } }

$d = new Symfony\Component\EventDispatcher\EventDispatcher;
$d->addListener('foo', new MyListener1);
$d->addListener('foo', new MyListener2);

var_dump($d->getListeners('foo'));
?>
--EXPECTF--
array(2) {
  [0]=>
  object(MyListener1)#2 (0) {
  }
  [1]=>
  object(MyListener2)#3 (0) {
  }
}