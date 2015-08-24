--TEST--
EventDispatcher basic dispatch()
--SKIPIF--
<?php
    if (!extension_loaded("symfony_eventdispatcher")) die("skip symfony_eventdispatcher is not loaded");
?>
--FILE--
<?php
class MyListener
{
	private $arg;

	public function __invoke()
	{
		var_dump(func_get_args());
	}
}

$d = new Symfony\Component\EventDispatcher\EventDispatcher;
$d->addListener('test-event', new MyListener, 10);

$d->dispatch('test-event');
?>
--EXPECTF--
array(3) {
  [0]=>
  object(Symfony\Component\EventDispatcher\Event)#%d (0) {
  }
  [1]=>
  string(10) "test-event"
  [2]=>
  object(Symfony\Component\EventDispatcher\EventDispatcher)#%d (0) {
  }
}
