--TEST--
Test HttpKernelInterface with register_argc_argv=1
--INI--
display_errors=1
register_argc_argv=1
--FILE--
<?php

// emulating PHP behavior with register_argc_argv=1
$_GET['-e_test'] = '';
$_SERVER['argc'] = 1;
$_SERVER['argv'] = [' ', '-e', 'test'];

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/kernel.php';

?>
--EXPECTF--
OK Kernel (env=dev) foo_bar
