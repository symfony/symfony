<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Runtime\StartedAppInterface;
use Symfony\Component\Runtime\StartedApp\ClosureStarted;
use Symfony\Component\Runtime\SymfonyRuntime;

require __DIR__.'/autoload.php';

$runtime = new class(['project_dir' => __DIR__]) extends SymfonyRuntime {
    public function start(object $kernel): StartedAppInterface
    {
        return new ClosureStarted(static function () use ($kernel): int {
            $kernel->handle(new Request())->send();
            echo "\n";
            $kernel->handle(new Request())->send();
            echo "\n";

            return 0;
        });
    }
};

$kernel = $runtime->resolve(require __DIR__.'/kernel.php')();
echo $runtime->start($kernel)();
