<?php

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

$componentRoot = $_SERVER['COMPONENT_ROOT'];

if (!is_file($file = $componentRoot.'/vendor/autoload.php')) {
    $file = $componentRoot.'/../../../../vendor/autoload.php';
}

require $file;

ob_start();
(new CliDumper('php://output'))->dump((new VarCloner())->cloneVar(123));
$dump = ob_get_clean();

fwrite(\STDOUT, \sprintf('tty=%s colors=%s', var_export(stream_isatty(\STDOUT), true), var_export(str_contains($dump, "\033["), true)));
