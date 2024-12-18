#!/usr/bin/env php
<?php declare(strict_types=1);

/**
 * This is the entry point for the module, called from the dagger engine.
 * Editing this file is highly discouraged and may stop your module from functioning entirely.
 */

use Symfony\Component\Console\Application;
use Dagger\Command\EntrypointCommand;

if (file_exists(__DIR__.'/../../autoload.php')) {
    // The usual location, since this file will reside in vendor/bin
    require __DIR__.'/../../autoload.php';
} else {
    // Useful when doing development on this package
    require __DIR__.'/vendor/autoload.php';
}

$console = new Application();

$console->add(new EntrypointCommand());
$console->setDefaultCommand('dagger:entrypoint');

$console->run();
