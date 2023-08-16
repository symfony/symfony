--TEST--
Test set ENV variable APP_RUNTIME_MODE from $_ENV
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/runtime_mode_from_env.php';

?>
--EXPECTF--
From context env, from $_ENV env
