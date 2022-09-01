<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\String\Resources\WcswidthDataGenerator;

error_reporting(\E_ALL);

set_error_handler(static function (int $type, string $msg, string $file, int $line): void {
    throw new \ErrorException($msg, 0, $type, $file, $line);
});

set_exception_handler(static function (Throwable $exception): void {
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

$autoload = __DIR__.'/../../vendor/autoload.php';

if (!file_exists($autoload)) {
    echo wordwrap('You should run "composer install" in the component before running this script.', 75)." Aborting.\n";

    exit(1);
}

require_once $autoload;

echo "Generating wcswidth tables data...\n";

(new WcswidthDataGenerator(dirname(__DIR__).'/data'))->generate();

echo "Done.\n";
