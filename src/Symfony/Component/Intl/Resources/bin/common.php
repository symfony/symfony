<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

define('LINE_WIDTH', 75);

define('LINE', str_repeat('-', LINE_WIDTH)."\n");

function bailout($message)
{
    echo wordwrap($message, LINE_WIDTH)." Aborting.\n";

    exit(1);
}

function strip_minor_versions($version)
{
    preg_match('/^(?P<version>[0-9]\.[0-9]|[0-9]{2,})/', $version, $matches);

    return $matches['version'];
}

function centered($text)
{
    $padding = (int) ((LINE_WIDTH - strlen($text)) / 2);

    return str_repeat(' ', $padding).$text;
}

function cd($dir)
{
    if (false === chdir($dir)) {
        bailout("Could not switch to directory $dir.");
    }
}

function run($command)
{
    exec($command, $output, $status);

    if (0 !== $status) {
        $output = implode("\n", $output);
        echo "Error while running:\n    ".getcwd().'$ '.$command."\nOutput:\n".LINE."$output\n".LINE;

        bailout("\"$command\" failed.");
    }
}

function get_icu_version_from_genrb($genrb)
{
    exec($genrb.' --version 2>&1', $output, $status);

    if (0 !== $status) {
        bailout($genrb.' failed.');
    }

    if (!preg_match('/ICU version ([\d\.]+)/', implode('', $output), $matches)) {
        return;
    }

    return $matches[1];
}

set_exception_handler(function (\Throwable $exception) {
    echo "\n";

    $cause = $exception;
    $root = true;

    while (null !== $cause) {
        if (!$root) {
            echo "Caused by\n";
        }

        echo get_class($cause).': '.$cause->getMessage()."\n";
        echo "\n";
        echo $cause->getFile().':'.$cause->getLine()."\n";
        foreach ($cause->getTrace() as $trace) {
            echo $trace['file'].':'.$trace['line']."\n";
        }
        echo "\n";

        $cause = $cause->getPrevious();
        $root = false;
    }
});
