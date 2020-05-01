<?php

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Runtime\RuntimeInterface;

require __DIR__.'/autoload.php';

return function (Application $app, Command $command, RuntimeInterface $runtime) {
    $app->setVersion('1.2.3');
    $app->setName('Hello console');
    $command->setDescription('Hello description ');
    $command->setName('my_command');

    $app->add($runtime->resolve(require __DIR__.'/command.php')());

    return $app;
};
