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
