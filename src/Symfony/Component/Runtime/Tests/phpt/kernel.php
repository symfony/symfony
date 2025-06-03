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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

require __DIR__.'/autoload.php';

class TestKernel implements HttpKernelInterface
{
    private string $env;
    private string $var;

    public function __construct(string $env, string $var)
    {
        $this->env = $env;
        $this->var = $var;
    }

    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = true): Response
    {
        return new Response('OK Kernel (env='.$this->env.') '.$this->var);
    }
}

return fn (array $context) => new TestKernel($context['APP_ENV'], $context['SOME_VAR']);
