--TEST--
Test symfony_debug_backtrace in case of fatal error
--SKIPIF--
<?php if (!extension_loaded("symfony_debug")) print "skip"; ?>
--FILE--
<?php

function bar()
{
    foo();
}

function foo()
{
    notexist();
}

function bt()
{
    print_r(symfony_debug_backtrace());

}

register_shutdown_function('bt');

bar();

?>
--EXPECTF--
Fatal error: Call to undefined function notexist() in %s on line %d
Array
(
    [0] => Array
        (
            [function] => bt
            [args] => Array
                (
                )

        )

    [1] => Array
        (
            [file] => %s
            [line] => %d
            [function bug] => foo
            [args] => Array
                (
                )

        )

    [2] => Array
        (
            [file] => %s
            [line] => %d
            [function] => bar
            [args] => Array
                (
                )

        )

)
