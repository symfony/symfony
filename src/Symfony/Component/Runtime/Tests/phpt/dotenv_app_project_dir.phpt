--TEST--
Dotenv sees APP_PROJECT_DIR during boot
--INI--
display_errors=1
--FILE--
<?php

require $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/dotenv_app_project_dir.php';

?>
--EXPECTF--
OK %s%eTests%ephpt
