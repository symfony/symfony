<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\Runner\Symfony;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RunnerInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HttpKernelRunner implements RunnerInterface
{
    public function __construct(
        private readonly HttpKernelInterface $kernel,
        private readonly Request $request,
        private readonly bool $debug = false,
    ) {
    }

    public function run(): int
    {
        $response = $this->kernel->handle($this->request);

        if (Kernel::VERSION_ID >= 60400) {
            $response->send(false);

            if (\function_exists('fastcgi_finish_request') && !$this->debug) {
                fastcgi_finish_request();
            } elseif (\function_exists('litespeed_finish_request') && !$this->debug) {
                litespeed_finish_request();
            } else {
                Response::closeOutputBuffers(0, true);
                flush();
            }
        } else {
            $response->send();
        }

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($this->request, $response);
        }

        return 0;
    }
}
