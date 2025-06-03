#!/usr/bin/env php
<?php
$argsPath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sendmail_args';

file_put_contents($argsPath, implode(' ', $argv));

print "Sending failed";
exit(42);
