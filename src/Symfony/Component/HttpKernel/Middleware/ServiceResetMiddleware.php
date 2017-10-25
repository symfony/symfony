<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Middleware;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * Clean up services between requests.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
class ServiceResetMiddleware implements HttpKernelInterface, TerminableInterface
{
    private $httpKernel;
    private $services;
    private $resetMethods;

    public function __construct(HttpKernelInterface $httpKernel, \Traversable $services, array $resetMethods)
    {
        $this->services = $services;
        $this->resetMethods = $resetMethods;
        $this->httpKernel = $httpKernel;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        if (self::MASTER_REQUEST === $type) {
            $this->resetServices();
        }

        return $this->httpKernel->handle($request);
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response)
    {
        if ($this->httpKernel instanceof TerminableInterface) {
            $this->httpKernel->terminate($request, $response);
        }
    }

    private function resetServices()
    {
        foreach ($this->services as $id => $service) {
            $method = $this->resetMethods[$id];
            $service->$method();
        }
    }
}
