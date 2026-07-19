<?php

use Symfony\Component\Console\Helper\TerminalInputHelper;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$helper = new TerminalInputHelper(\STDIN);

// Simulate a captured state that "stty" refuses to parse back (as can happen with some
// nested pty implementations), to exercise the "stty sane" fallback in finish().
$property = new ReflectionProperty(TerminalInputHelper::class, 'initialState');
$property->setValue($helper, 'not-a-valid-stty-state');

// Put the terminal in the raw-ish state the question helpers use while reading keypresses.
shell_exec('stty -icanon -echo');

$helper->finish();
