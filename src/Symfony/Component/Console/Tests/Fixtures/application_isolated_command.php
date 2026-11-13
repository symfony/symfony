<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

$vendor = __DIR__;
while (!file_exists($vendor.'/vendor')) {
    $vendor = dirname($vendor);
}
require $vendor.'/vendor/autoload.php';

$application = new Application();
$application->addCommand((new Command('test:command'))
    ->addOption('exit', null, InputOption::VALUE_REQUIRED, '', 0)
    ->addOption('sleep', null, InputOption::VALUE_REQUIRED, '', 0)
    ->addOption('stderr')
    ->addOption('throw-unrecoverable')
    ->addOption('marker-file', null, InputOption::VALUE_REQUIRED)
    ->setCode(static function (InputInterface $input, OutputInterface $output): int {
        if ($markerFile = $input->getOption('marker-file')) {
            file_put_contents($markerFile, 'started');
        }

        $output->write('some message');

        if ($input->getOption('stderr') && $output instanceof ConsoleOutputInterface) {
            $output->getErrorOutput()->write('some error');
        }

        if ($input->getOption('throw-unrecoverable')) {
            throw new UnrecoverableMessageHandlingException('Unrecoverable exception message');
        }

        if ($sleep = (int) $input->getOption('sleep')) {
            sleep($sleep);
        }

        if ($markerFile) {
            file_put_contents($markerFile, ' done', \FILE_APPEND);
        }

        return (int) $input->getOption('exit');
    })
);

$application->run();
