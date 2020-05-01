<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

require __DIR__.'/autoload.php';

return function (array $context) {
    return new class($context['SOME_VAR']) implements HttpKernelInterface {
        private $var;

        public function __construct(string $var)
        {
            $this->var = $var;
        }

        public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
        {
            return new Response('OK Kernel '.$this->var);
        }
    };
};
