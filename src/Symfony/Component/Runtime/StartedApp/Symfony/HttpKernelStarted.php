<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Runtime\StartedApp\Symfony;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Runtime\RuntimeInterface;
use Symfony\Component\Runtime\StartedAppInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class HttpKernelStarted implements StartedAppInterface
{
    private $kernel;
    private $request;
    private $runtime;

    public function __construct(HttpKernelInterface $kernel, Request $request, RuntimeInterface $runtime)
    {
        $this->kernel = $kernel;
        $this->request = $request;
        $this->runtime = $runtime;
    }

    public function __invoke(): int
    {
        $response = $this->kernel->handle($this->request);
        $status = $this->runtime->start($response)();

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($this->request, $response);
        }

        return $status;
    }
}
