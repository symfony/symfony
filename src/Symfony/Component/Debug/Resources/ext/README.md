Symfony Debug Extension
=======================

This extension publishes several functions to help building powerful debugging tools.

symfony_zval_info()
-------------------

- exposes zval_hash/refcounts, allowing e.g. efficient exploration of arbitrary structures in PHP,
- does work with references, preventing memory copying.

Its behavior is about the same as:

```php
<?php

function symfony_zval_info($key, $array, $options = 0)
{

    // $options is currently not used, but could be in future version.

    if (!array_key_exists($key, $array)) {
        return null;
    }

    $info = array(
        'type' => gettype($array[$key]),
        'zval_hash' => /* hashed memory address of $array[$key] */,
        'zval_refcount' => /* internal zval refcount of $array[$key] */,
        'zval_isref' => /* is_ref status of $array[$key] */,
    );

    switch ($info['type']) {
        case 'object':
            $info += array(
                'object_class' => get_class($array[$key]),
                'object_refcount' => /* internal object refcount of $array[$key] */,
                'object_hash' => spl_object_hash($array[$key]),
                'object_handle' => /* internal object handle $array[$key] */,
            );
            break;

        case 'resource':
            $info += array(
                'resource_handle' => (int) $array[$key],
                'resource_type' => get_resource_type($array[$key]),
                'resource_refcount' => /* internal resource refcount of $array[$key] */,
            );
            break;

        case 'array':
            $info += array(
                'array_count' => count($array[$key]),
            );
            break;

        case 'string':
            $info += array(
                'strlen' => strlen($array[$key]),
            );
            break;
    }

    return $info;
}
```

symfony_debug_backtrace()
-------------------------

This function works like debug_backtrace(), except that it can fetch the full backtrace in case of fatal errors:

```php
function foo() { fatal(); }
function bar() { foo(); }

function sd() { var_dump(symfony_debug_backtrace()); }

register_shutdown_function('sd');

bar();

/* Will output
Fatal error: Call to undefined function fatal() in foo.php on line 42
array(3) {
  [0]=>
  array(2) {
    ["function"]=>
    string(2) "sd"
    ["args"]=>
    array(0) {
    }
  }
  [1]=>
  array(4) {
    ["file"]=>
    string(7) "foo.php"
    ["line"]=>
    int(1)
    ["function"]=>
    string(3) "foo"
    ["args"]=>
    array(0) {
    }
  }
  [2]=>
  array(4) {
    ["file"]=>
    string(102) "foo.php"
    ["line"]=>
    int(2)
    ["function"]=>
    string(3) "bar"
    ["args"]=>
    array(0) {
    }
  }
}
*/
```

symfony_debug_object_tracer_set_logger(Psr\Log\LoggerInterface $logger)
---------------------------------------------------------

Collector for object traces. Object traces is a tracer - enabled when a logger object is passed to symfony_debug_set_logger() - that traces objects creation / cloning and destruction from a PHP instance.
The PSR3 Logger is used to log object traces, the debug() method is used, receiving a general message, and as context, the object class, the object handle, the filename and line where happening (except in shutdown sequence) and the type of event beeing logged : SYMFONY_DEBUG_OBJECT_TRACE_TYPE_NEW (object creation), SYMFONY_DEBUG_OBJECT_TRACE_TYPE_CLONE (object clone) or SYMFONY_DEBUG_OBJECT_TRACE_TYPE_DESTROY (object destruction).

```php
class TestLog implements Psr\Log\LoggerInterface {
    public function emergency($message, array $context = array()) { }
    public function alert($message, array $context = array()) { }
    public function critical($message, array $context = array()) { }
    public function error($message, array $context = array()) { }
    public function warning($message, array $context = array()) { }
    public function notice($message, array $context = array()) { }
    public function info($message, array $context = array()) { }
    public function debug($message, array $context = array()) { printf("$message \n"); }
    public function log($level, $message, array $context = array()) { }
}

/* Exemple filtering only on object destruction */
class LogFilter extends TestLog
{
	public function debug($message, array $context = array())
	{ 
		if($context['trace_type'] & SYMFONY_DEBUG_OBJECT_TRACE_TYPE_DESTROY) {
			printf('destroying');
		}
	}
}

$log = new TestLog;
symfony_debug_object_tracer_set_logger($log); 

$a = new StdClass;

$b = clone $a;

unset($b);

/* This will output :
Creating an object of class stdClass in foo.php:15 
Cloning object #2 of class stdClass in foo.php:17 
Destroying object #3 of class stdClass at foo.php:19 
Destroying object #2 of class stdClass at [no active file]:0
*/
```

symfony_debug_get_error_handler() - symfony_debug_get_error_handlers()
----------------------------------------------------------

Simply dumps the current user error handler(s).

```php

function my_eh() { }

set_error_handler(function () { });
set_error_handler('my_eh');

var_dump(symfony_debug_get_error_handler());
var_dump(symfony_debug_get_error_handlers());

/*
string(5) "my_eh"

array(2) {
  [0]=>
  object(Closure)#1 (0) {
  }
  [1]=>
  string(5) "my_eh"
}
*/

```

symfony_debug_enable_var_dumper_dump()
--------------------------------------

Replaces PHP's var_dump() function by Symfony\\Component\\VarDumper\\VarDumper::dump();
Supports Xdebug.

Usage
-----

The extension is compatible with ZTS mode, and should be supported by PHP5.3, 5.4, 5.5 and 5.6.
To enable the extension from source, run:

```
    phpize
    ./configure
    make
    sudo make install
```
