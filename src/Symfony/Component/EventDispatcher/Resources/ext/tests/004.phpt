--TEST--
EventDispatcher subscribers API()
--SKIPIF--
<?php
    if (!extension_loaded("symfony_eventdispatcher")) die("skip symfony_eventdispatcher is not loaded");
?>
--FILE--
<?php
class Subscriber implements Symfony\Component\EventDispatcher\EventSubscriberInterface
{
    static $events = array( 'foo-event' => array( array('foocb1', 42), array('foocb2', 422) ), 'bar-event' => array('barcb1', 33), 'baz-event' => 'bazcb' );
    
	public static function getSubscribedEvents()
	{
	     return self::$events;
	}
}

$d = new Symfony\Component\EventDispatcher\EventDispatcher;
$d->addSubscriber($s = new Subscriber);

var_dump($d->getListeners());
$d->removeSubscriber($s);
var_dump($d->getListeners());
?>
--EXPECTF--
array(3) {
  ["foo-event"]=>
  array(2) {
    [0]=>
    array(2) {
      [0]=>
      object(Subscriber)#2 (0) {
      }
      [1]=>
      string(6) "foocb2"
    }
    [1]=>
    array(2) {
      [0]=>
      object(Subscriber)#2 (0) {
      }
      [1]=>
      string(6) "foocb1"
    }
  }
  ["bar-event"]=>
  array(1) {
    [0]=>
    array(2) {
      [0]=>
      object(Subscriber)#2 (0) {
      }
      [1]=>
      string(6) "barcb1"
    }
  }
  ["baz-event"]=>
  array(1) {
    [0]=>
    array(2) {
      [0]=>
      object(Subscriber)#2 (0) {
      }
      [1]=>
      string(5) "bazcb"
    }
  }
}
array(3) {
  ["foo-event"]=>
  array(0) {
  }
  ["bar-event"]=>
  array(0) {
  }
  ["baz-event"]=>
  array(0) {
  }
}