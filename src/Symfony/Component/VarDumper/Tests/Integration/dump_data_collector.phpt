--TEST--
Test integration with Symfony's DumpDataCollector
--FILE--
<?php
putenv('NO_COLOR=1');

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = \dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;

VarDumper::setHandler(function ($var, string $label = null) {
    $dumper = new DumpDataCollector();
    $cloner = new VarCloner();
    $handler = function ($var, string $label = null) use ($dumper, $cloner) {
        $var = $cloner->cloneVar($var);
        if (null !== $label) {
            $var = $var->withContext(['label' => $label]);
        }

        $dumper->dump($var);
    };
    VarDumper::setHandler($handler);
    $handler($var, $label);
});

$schemas = new \ArrayObject();
dump($schemas);
$schemas['X'] = new \ArrayObject(['type' => 'object']);

--EXPECTF--
ArrayObject {#%d
  -storage: []
  flag::STD_PROP_LIST: false
  flag::ARRAY_AS_PROPS: false
  iteratorClass: "ArrayIterator"
}
