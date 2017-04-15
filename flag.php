<?php

/**
 * This file shows some use examples of Flag component, and will be removed if proposal is accepted.
 */

namespace Demo;

require 'vendor/autoload.php';

use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Flag\Flag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\Yaml\Yaml;

$logger = new ConsoleLogger(new ConsoleOutput(ConsoleOutput::VERBOSITY_DEBUG));

echo "\nOverview\n";
$flag = Flag::create(Yaml::class);
$flag->setLogger($logger);
$flag
    ->add(Yaml::DUMP_OBJECT)
    ->add(Yaml::PARSE_DATETIME)
    ->remove(Yaml::DUMP_OBJECT)
;
dump(
    $flag->has(Yaml::DUMP_OBJECT),
    $flag->has(Yaml::PARSE_DATETIME),
    $flag->get()
);
$flag->set(100);
foreach ($flag as $k => $v) {
    echo "$k => $v ";
}

echo "\n\nExample\n";
class Color
{
    const RED = 1;
    const GREEN = 2;
    const YELLOW = 3;
    public $flag;

    public function __construct($logger)
    {
        $this->flag = Flag::create(self::class);
        $this->flag->setLogger($logger);
    }
}
(new Color($logger))->flag
    ->add(Color::RED)
    ->add(Color::GREEN)
    ->remove(Color::GREEN)
;

echo "\nPrefix\n";
$flag = Flag::create(Caster::class, 'EXCLUDE_');
$flag->setLogger($logger);
$flag
    ->add(Caster::EXCLUDE_EMPTY)
    ->add(Caster::EXCLUDE_PRIVATE)
    ->add(Caster::EXCLUDE_NOT_IMPORTANT)
    ->remove(Caster::EXCLUDE_NOT_IMPORTANT)
;

echo "\nHierarchical\n";
$flag = Flag::create(Output::class, 'VERBOSITY_', true);
$flag->setLogger($logger);
$flag
    ->add(Output::VERBOSITY_VERBOSE)
    ->add(Output::VERBOSITY_DEBUG)
    ->remove(Output::VERBOSITY_DEBUG)
;

echo "\nGlobal space\n";
$flag = Flag::create(null, 'E_');
$flag->setLogger($logger);
$flag
    ->add(E_ALL)
    ->set(0)
    ->add(E_USER_ERROR)
    ->add(E_USER_DEPRECATED)
    ->remove(E_USER_DEPRECATED)
;

echo "\nBinarizedFlag\n";
$flag = Flag::create(Request::class, 'METHOD_');
$flag->setLogger($logger);
$flag
    ->add(Request::METHOD_GET)
    ->add(Request::METHOD_POST)
    ->add(Request::METHOD_PUT)
    ->remove(Request::METHOD_PUT)
;

echo "\nStandalone\n";
$flag = new Flag();
$flag->setLogger($logger);
$flag
    ->add(8)
    ->add(32)
    ->remove(32)
;
echo "\n";
$flag = Flag::create();
$flag->setLogger($logger);
$flag
    ->add('a')
    ->add('b')
    ->remove('b')
;
