<?php
namespace Symfony\Component\HttpKernel\IncludeProxy;

use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * @author Sebastian Krebs <krebs.seb@gmail.com>
 */
interface IncludeProxyInterface extends HttpKernelInterface,TerminableInterface
{
}
