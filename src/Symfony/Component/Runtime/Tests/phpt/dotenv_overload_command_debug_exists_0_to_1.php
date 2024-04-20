<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

$_SERVER['DEBUG_ENABLED'] = '0';
$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'debug_var_name' => 'DEBUG_ENABLED',
    'dotenv_overload' => true,
];

require __DIR__.'/autoload.php';

return static fn (Command $command, OutputInterface $output, array $context): Command => $command->setCode(static function () use ($output, $context): void {
    $output->writeln($context['DEBUG_ENABLED']);
});
