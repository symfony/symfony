<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Runtime\Runner\ClosureRunner;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

require __DIR__.'/autoload.php';

$runtime = new class(['project_dir' => __DIR__]) extends SymfonyRuntime {
    public function getRunner(?object $kernel): RunnerInterface
    {
        return new ClosureRunner(static function () use ($kernel): int {
            $kernel->handle(new Request())->send();
            echo "\n";
            $kernel->handle(new Request())->send();
            echo "\n";

            return 0;
        });
    }
};

[$app, $args] = $runtime->getResolver(require __DIR__.'/kernel.php')->resolve();
echo $runtime->getRunner($app(...$args))->run();
