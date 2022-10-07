<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if ('cli' !== \PHP_SAPI) {
    throw new Exception('This script must be run from the command line.');
}

define('LINE_WIDTH', 75);

define('LINE', str_repeat('-', LINE_WIDTH)."\n");

function bailout(string $message)
{
    echo wordwrap($message, LINE_WIDTH)." Aborting.\n";

    exit(1);
}

function strip_minor_versions(string $version)
{
    preg_match('/^(?P<version>[0-9]\.[0-9]|[0-9]{2,})/', $version, $matches);

    return $matches['version'];
}

function centered(string $text)
{
    $padding = (int) ((LINE_WIDTH - strlen($text)) / 2);

    return str_repeat(' ', $padding).$text;
}

function cd(string $dir)
{
    if (false === chdir($dir)) {
        bailout("Could not switch to directory $dir.");
    }
}

function run(string $command)
{
    exec($command, $output, $status);

    if (0 !== $status) {
        $output = implode("\n", $output);
        echo "Error while running:\n    ".getcwd().'$ '.$command."\nOutput:\n".LINE."$output\n".LINE;

        bailout("\"$command\" failed.");
    }
}

function get_icu_version_from_genrb(string $genrb)
{
    exec($genrb.' --version - 2>&1', $output, $status);

    if (0 !== $status) {
        bailout($genrb.' failed.');
    }

    if (!preg_match('/ICU version ([\d\.]+)/', implode('', $output), $matches)) {
        return null;
    }

    return $matches[1];
}

error_reporting(\E_ALL);

set_error_handler(function (int $type, string $msg, string $file, int $line) {
    throw new \ErrorException($msg, 0, $type, $file, $line);
});

set_exception_handler(function (Throwable $exception) {
    echo "\n";

    $cause = $exception;
    $root = true;

    while (null !== $cause) {
        if (!$root) {
            echo "Caused by\n";
        }

        echo $cause::class.': '.$cause->getMessage()."\n";
        echo "\n";
        echo $cause->getFile().':'.$cause->getLine()."\n";
        echo $cause->getTraceAsString()."\n";

        $cause = $cause->getPrevious();
        $root = false;
    }
});
