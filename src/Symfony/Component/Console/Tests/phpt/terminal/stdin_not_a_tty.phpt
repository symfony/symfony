--TEST--
Terminal does not run stty when STDIN is not a terminal
--SKIPIF--
<?php if ('\\' === DIRECTORY_SEPARATOR) echo 'Skipped: Windows uses other probes.'; ?>
--FILE--
<?php

use Symfony\Component\Console\Terminal;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$dir = sys_get_temp_dir().'/sf_console_stty_'.getmypid();
@mkdir($dir);
file_put_contents($dir.'/stty', "#!/bin/sh\necho 'rows 12; columns 34;'\n");
chmod($dir.'/stty', 0755);
putenv('PATH='.$dir.':'.getenv('PATH'));
putenv('COLUMNS');
putenv('LINES');

$terminal = new Terminal();
var_dump($terminal->getWidth(), $terminal->getHeight());

unlink($dir.'/stty');
rmdir($dir);
?>
--EXPECT--
int(80)
int(50)
