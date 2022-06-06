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
use Symfony\Component\Runtime\RuntimeInterface;

require __DIR__.'/autoload.php';

return function (Application $app, Command $command, RuntimeInterface $runtime) {
    $app->setVersion('1.2.3');
    $app->setName('Hello console');

    $command->setDescription('Hello description ');
    $command->setName('my_command');

    [$cmd, $args] = $runtime->getResolver(require __DIR__.'/command.php')->resolve();
    $app->add($cmd(...$args));

    return $app;
};
