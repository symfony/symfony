<?php

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernel as BaseHttpKernel;
use Symfony\Component\EventDispatcher\EventDispatcher as BaseEventDispatcher;

/**
 * This HttpKernel is used to manage scope changes of the DI container.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class HttpKernel extends BaseHttpKernel
{
    protected $container;

    public function __construct(ContainerInterface $container, BaseEventDispatcher $eventDispatcher, ControllerResolverInterface $controllerResolver)
    {
        parent::__construct($eventDispatcher, $controllerResolver);

        $this->container = $container;
    }

    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        $this->container->enterScope('request');
        $this->container->set('request', $request, 'request');

        try {
            $response = parent::handle($request, $type, $catch);
        } catch (\Exception $e) {
            $this->container->leaveScope('request');

            throw $e;
        }

        $this->container->leaveScope('request');

        return $response;
    }
}
