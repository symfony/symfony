<?php
namespace Symfony\Component\HttpKernel\IncludeProxy;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
interface IncludeStrategyInterface {
    public function getName ();
    public function handle(HttpKernelInterface $kernel, Request $request, Response $response);
}
