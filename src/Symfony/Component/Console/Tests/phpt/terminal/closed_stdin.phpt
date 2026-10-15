--TEST--
Terminal falls back to the default dimensions when STDIN is closed
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

putenv('COLUMNS');
putenv('LINES');
fclose(\STDIN);

$terminal = new Terminal();
var_dump($terminal->getWidth(), $terminal->getHeight());
?>
--EXPECT--
int(80)
int(50)
