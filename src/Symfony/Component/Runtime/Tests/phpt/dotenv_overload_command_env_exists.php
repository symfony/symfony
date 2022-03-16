<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

$_SERVER['ENV_MODE'] = 'notfoo';
$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'env_var_name' => 'ENV_MODE',
    'dotenv_overload' => true,
];

require __DIR__.'/autoload.php';

return static function (Command $command, OutputInterface $output, array $context): Command {
    return $command->setCode(static function () use ($output, $context): void {
        $output->writeln($context['ENV_MODE']);
    });
};
