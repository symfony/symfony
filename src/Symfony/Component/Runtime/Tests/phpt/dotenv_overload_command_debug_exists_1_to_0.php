<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

$_SERVER['DEBUG_MODE'] = '1';
$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'debug_var_name' => 'DEBUG_MODE',
    'dotenv_overload' => true,
];

require __DIR__.'/autoload.php';

return static function (Command $command, OutputInterface $output, array $context): Command {
    return $command->setCode(static function () use ($output, $context): void {
        $output->writeln($context['DEBUG_MODE']);
    });
};
