<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Kernel\Kernel as BaseKernel;

/**
 * The HTTP Kernel is the heart of the Symfony system to handle HTTP request.
 *
 * It manages an environment made of application kernel and bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Kernel extends BaseKernel implements KernelInterface, RebootableInterface, TerminableInterface
{
    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response)
    {
        if (false === $this->booted) {
            return;
        }

        if ($this->getHttpKernel() instanceof TerminableInterface) {
            $this->getHttpKernel()->terminate($request, $response);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this->boot();
        $this->enterScope();

        try {
            return $this->getHttpKernel()->handle($request, $type, $catch);
        } finally {
            $this->leaveScope();
        }
    }

    /**
     * Gets a HTTP kernel from the container.
     *
     * @return HttpKernel
     */
    protected function getHttpKernel()
    {
        return $this->container->get('http_kernel');
    }
}
